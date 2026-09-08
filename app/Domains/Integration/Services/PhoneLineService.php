<?php

declare(strict_types=1);

namespace App\Domains\Integration\Services;

use App\Domains\Account\Services\ActivityLogService;
use App\Enums\RecordStatus;
use App\Models\WhatsappLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Manages all "Manage your Phone Numbers" operations:
 *  - Listing secondary lines (non-default)
 *  - Setting/changing the per-line Number Access password
 *  - Login-as-number (session lock to a single line context)
 *  - Exiting number-specific context
 *  - Promoting a secondary line to default
 *
 * The line context is held in the session:
 *   line_context_locked   → bool
 *   line_context_line_id  → int (WhatsappLine.id)
 */
class PhoneLineService
{
    /** Session key: indicates the session is locked to a specific line */
    public const SESSION_LOCKED    = 'line_context_locked';

    /** Session key: the WhatsappLine.id for the locked context */
    public const SESSION_LINE_ID   = 'line_context_line_id';

    public function __construct(
        private readonly ActivityLogService $activityLogService,
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
            'subject_id'   => $line->id,
        ]);

        return $line->fresh();
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
            'subject_id'   => $line->id,
        ]);

        return $line->fresh();
    }

    // ─── Line context (session) ───────────────────────────────────────────────

    /**
     * Lock the session to a specific line context (Login as this number).
     * Verifies password before locking.
     *
     * @throws \InvalidArgumentException on wrong password or line not connected
     */
    public function loginAsLine(WhatsappLine $line, string $password): void
    {
        if (! $line->isConnected()) {
            throw new \InvalidArgumentException('This number is not connected yet.');
        }

        if (! $line->hasLinePassword()) {
            throw new \InvalidArgumentException('Set a Number Access password for this number before using Login as this number.');
        }

        if (! $line->checkLinePassword($password)) {
            throw new \InvalidArgumentException('Incorrect password for this number.');
        }

        session([
            self::SESSION_LOCKED  => true,
            self::SESSION_LINE_ID => $line->id,
        ]);

        $this->activityLogService->log('phone_line.context_entered', [
            'subject_type' => WhatsappLine::class,
            'subject_id'   => $line->id,
        ]);
    }

    /**
     * Exit number-specific context and return to all-lines mode.
     */
    public function exitLineContext(): void
    {
        session()->forget([self::SESSION_LOCKED, self::SESSION_LINE_ID]);
    }

    /**
     * Find a line by normalised phone number (strips non-digits, then matches).
     * Used by the public Line Login page.
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
                // Allow matching last N digits (e.g. 10 vs 12 digit forms)
                return str_ends_with((string) $lineDigits, $digits)
                    || str_ends_with($digits, (string) $lineDigits);
            });
    }

    /**
     * Public Line Login: look up a line by phone number and verify password.
     * On success, locks the session to that line's context.
     *
     * @throws \InvalidArgumentException when phone/password do not match
     */
    public function lineLoginByPhone(string $rawPhone, string $password): WhatsappLine
    {
        $line = $this->findLineByPhone($rawPhone);

        if (! $line instanceof WhatsappLine) {
            throw new \InvalidArgumentException('No WhatsApp number found matching that phone number.');
        }

        if (! $line->hasLinePassword()) {
            throw new \InvalidArgumentException('This number does not have a Number Access password set. Contact the account owner.');
        }

        if (! $line->checkLinePassword($password)) {
            throw new \InvalidArgumentException('Incorrect password for this number.');
        }

        session([
            self::SESSION_LOCKED  => true,
            self::SESSION_LINE_ID => $line->id,
        ]);

        $this->activityLogService->log('phone_line.public_login', [
            'subject_type' => WhatsappLine::class,
            'subject_id'   => $line->id,
        ]);

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
     * The currently locked line, or null if not locked.
     */
    public function lockedLine(): ?WhatsappLine
    {
        $lineId = session(self::SESSION_LINE_ID);

        return $lineId ? WhatsappLine::query()->find((int) $lineId) : null;
    }
}
