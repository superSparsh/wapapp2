<?php

declare(strict_types=1);

namespace App\Domains\Integration\Services;

use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Auth\Services\TenantResolver;
use App\Domains\Auth\Support\AuthSession;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Manages all "Manage your Phone Numbers" operations:
 *  - Listing secondary lines (non-default)
 *  - Setting/changing the per-line Number Access password
 *  - Login-as-number (session lock to a single line context)
 *  - Public number login (auth owner + lock to that line only)
 *  - Exiting number-specific context
 *  - Promoting a secondary line to default
 *
 * Session keys:
 *   line_context_locked   → bool
 *   line_context_line_id  → int (WhatsappLine.id)
 *   line_direct_login     → bool (public /line-login session; exit logs out)
 */
class PhoneLineService
{
    /** Session key: indicates the session is locked to a specific line */
    public const SESSION_LOCKED = 'line_context_locked';

    /** Session key: the WhatsappLine.id for the locked context */
    public const SESSION_LINE_ID = 'line_context_line_id';

    /** Session key: true when signed in via public /line-login (not owner "Open Inbox") */
    public const SESSION_DIRECT_LOGIN = 'line_direct_login';

    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly WhatsappLineRegistryService $lineRegistry,
        private readonly TenantResolver $tenantResolver,
    ) {}

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * All lines for this tenant ordered by default-first then id.
     *
     * @return Collection<int, WhatsappLine>
     */
    public function allLines(): Collection
    {
        return WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * Secondary (non-default) lines only — these appear on the manage page.
     *
     * @return Collection<int, WhatsappLine>
     */
    public function secondaryLines(): Collection
    {
        return WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->where('is_default', false)
            ->orderBy('id')
            ->get();
    }

    /**
     * The default line for this tenant.
     */
    public function defaultLine(): ?WhatsappLine
    {
        return WhatsappLine::query()
            ->where('is_default', true)
            ->first()
            ?? WhatsappLine::query()->first();
    }

    /**
     * Find a line by its primary key (tenant-scoped — TenantModel handles this).
     */
    public function findLine(int $id): ?WhatsappLine
    {
        return WhatsappLine::query()->find($id);
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    /**
     * Set or update the Number Access password for a secondary line.
     * The value stored is bcrypt-hashed — the plain text is never retained.
     */
    public function setPassword(WhatsappLine $line, string $plainPassword): WhatsappLine
    {
        $line->update(['line_password' => Hash::make($plainPassword)]);

        $this->activityLogService->log('phone_line.password_set', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
        ]);

        return $line->fresh() ?? $line;
    }

    /**
     * Promote a secondary line to the tenant's default line.
     * Wrapped in a transaction: clears all other is_default flags atomically.
     */
    public function setAsDefault(WhatsappLine $line): WhatsappLine
    {
        DB::transaction(function () use ($line): void {
            WhatsappLine::query()->update(['is_default' => false]);
            $line->update(['is_default' => true]);
        });

        $this->activityLogService->log('phone_line.default_changed', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
        ]);

        return $line->fresh() ?? $line;
    }

    // ─── Line context (session) ───────────────────────────────────────────────

    /**
     * Lock the session to a specific line context (owner "Open Inbox for this number").
     * Verifies password before locking. Owner stays logged in as themselves.
     *
     * @throws \InvalidArgumentException on wrong password or line not connected
     */
    public function loginAsLine(WhatsappLine $line, string $password): void
    {
        if (! $line->isConnected()) {
            throw new \InvalidArgumentException('This number is not connected yet.');
        }

        if (! $line->hasLinePassword()) {
            throw new \InvalidArgumentException('Set a Number Access password for this number before opening its Inbox.');
        }

        if (! $line->checkLinePassword($password)) {
            throw new \InvalidArgumentException('Incorrect password for this number.');
        }

        $this->enterLineContext($line, directLogin: false);

        $this->activityLogService->log('phone_line.context_entered', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
        ]);
    }

    /**
     * Exit number-specific context.
     *
     * @return bool True when the session was a public direct line login (caller should log out).
     */
    public function exitLineContext(): bool
    {
        $wasDirect = self::isDirectLogin();

        session()->forget([
            self::SESSION_LOCKED,
            self::SESSION_LINE_ID,
            self::SESSION_DIRECT_LOGIN,
            'inbox_selected_line_uuid',
        ]);

        return $wasDirect;
    }

    /**
     * Find a line by normalised phone number (strips non-digits, then matches).
     * Requires an initialized tenant connection.
     */
    public function findLineByPhone(string $rawPhone): ?WhatsappLine
    {
        $digits = preg_replace('/\D/', '', $rawPhone);

        if (blank($digits)) {
            return null;
        }

        return WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->get()
            ->first(function (WhatsappLine $line) use ($digits): bool {
                $lineDigits = preg_replace('/\D/', '', (string) $line->phone);

                return str_ends_with((string) $lineDigits, $digits)
                    || str_ends_with($digits, (string) $lineDigits);
            });
    }

    /**
     * Public /line-login: resolve tenant by phone registry, authenticate as owner,
     * and lock the session so Inbox/campaigns only show that number.
     *
     * @throws \InvalidArgumentException when phone/password do not match
     */
    public function lineLoginByPhone(string $rawPhone, string $password): WhatsappLine
    {
        $resolved = $this->lineRegistry->resolveByBusinessPhone($rawPhone);

        if ($resolved === null && tenancy()->initialized) {
            // Tests / already-tenant context: fall back to local lookup.
            $line = $this->findLineByPhone($rawPhone);
            if ($line instanceof WhatsappLine) {
                $this->assertLinePassword($line, $password);
                $this->authenticateOwnerForLineLogin();
                $this->enterLineContext($line, directLogin: true);
                $this->logPublicLogin($line);

                return $line;
            }
        }

        if ($resolved === null) {
            throw new \InvalidArgumentException('Invalid phone number or password.');
        }

        tenancy()->initialize($resolved['tenant']);

        $line = WhatsappLine::query()->find($resolved['line_id'])
            ?? $this->findLineByPhone($rawPhone);

        if (! $line instanceof WhatsappLine) {
            throw new \InvalidArgumentException('Invalid phone number or password.');
        }

        $this->assertLinePassword($line, $password);
        $this->authenticateOwnerForLineLogin($resolved['tenant']->id);
        $this->enterLineContext($line, directLogin: true);
        $this->logPublicLogin($line);

        return $line;
    }

    /**
     * Whether the current session is locked to a specific line.
     */
    public static function isLocked(): bool
    {
        return (bool) session(self::SESSION_LOCKED, false);
    }

    /**
     * Whether this lock came from public /line-login (not owner Open Inbox).
     */
    public static function isDirectLogin(): bool
    {
        return filter_var(session(self::SESSION_DIRECT_LOGIN, false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The currently locked line, or null if not locked.
     */
    public function lockedLine(): ?WhatsappLine
    {
        $lineId = session(self::SESSION_LINE_ID);

        return $lineId ? WhatsappLine::query()->find((int) $lineId) : null;
    }

    /**
     * Locked line id for inbox filtering, or null when not locked.
     */
    public static function lockedLineId(): ?int
    {
        if (! self::isLocked()) {
            return null;
        }

        $lineId = (int) session(self::SESSION_LINE_ID, 0);

        return $lineId > 0 ? $lineId : null;
    }

    private function enterLineContext(WhatsappLine $line, bool $directLogin): void
    {
        session([
            self::SESSION_LOCKED => true,
            self::SESSION_LINE_ID => $line->id,
            self::SESSION_DIRECT_LOGIN => $directLogin,
            'inbox_selected_line_uuid' => $line->uuid,
        ]);
    }

    private function assertLinePassword(WhatsappLine $line, string $password): void
    {
        if (! $line->isConnected()) {
            throw new \InvalidArgumentException('This number is not connected yet.');
        }

        if (! $line->hasLinePassword() || ! $line->checkLinePassword($password)) {
            throw new \InvalidArgumentException('Invalid phone number or password.');
        }
    }

    private function authenticateOwnerForLineLogin(?string $tenantId = null): void
    {
        $owner = User::query()
            ->where('role', UserRole::Owner)
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if (! $owner instanceof User) {
            throw new \InvalidArgumentException('No account found for this number. Contact the account owner.');
        }

        Auth::guard('web')->login($owner);
        session()->regenerate();

        $tenantId ??= (string) (tenant('id') ?? '');
        if ($tenantId !== '') {
            $this->tenantResolver->storeInSession($tenantId, 'web');
        }

        // Number operators use line password — skip owner 2FA for this session.
        session([AuthSession::TWO_FACTOR_VERIFIED => true]);

        $owner->forceFill(['last_login_at' => now()])->save();
    }

    private function logPublicLogin(WhatsappLine $line): void
    {
        $this->activityLogService->log('phone_line.public_login', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
        ]);
    }
}
