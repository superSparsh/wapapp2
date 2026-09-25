<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Domains\Team\Support\TeamModuleActivityLabel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityLogService
{
    /** @var array<string, string> */
    public const ACTIONS = [
        'auth.login.success' => 'Login — successful',
        'auth.logout' => 'Logout',
        'profile.updated' => 'Profile — updated',
        'security.2fa.enabled' => '2FA — enabled',
        'security.2fa.disabled' => '2FA — disabled',
        'security.2fa.recovery_regenerated' => '2FA — backup recovery codes regenerated',
        'auth.2fa.challenge_success' => '2FA — verification passed',
        'auth.2fa.challenge_failed' => '2FA — verification failed',
        'auth.2fa.recovery_used' => '2FA — recovery code used at login',
        'security.api_token.renewed' => 'API token — renewed',
        'billing.subscription.cancelled' => 'Subscription — cancelled',
        'billing.subscription.paid' => 'Subscription — payment completed',
        'billing.wallet.recharge' => 'Wallet — Razorpay recharge completed',
        'integration.profile.updated' => 'WABA profile — updated',
        'integration.sync.completed' => 'WhatsApp business data — synced',
        'integration.phone.added' => 'WhatsApp number — added',
        'phone_line.password_set' => 'Number access password — saved',
        'phone_line.default_changed' => 'Default WhatsApp number — changed',
        'phone_line.context_entered' => 'Number-specific access — entered',
        'phone_line.public_login' => 'Number access — signed in',
        'data.export.requested' => 'Data export — requested',
        'data.deletion.scheduled' => 'Data deletion — scheduled',
        'data.deletion.cancelled' => 'Data deletion — cancelled',
        'data.deletion.completed' => 'Data deletion — completed',
    ];

    public static function labelFor(string $action, ?string $description = null): string
    {
        if (isset(self::ACTIONS[$action])) {
            return self::ACTIONS[$action];
        }

        $teamModule = TeamModuleActivityLabel::parseAction($action);
        if ($teamModule !== null) {
            $label = TeamModuleActivityLabel::describe($teamModule[0], $teamModule[1]);
            if (filled($description) && preg_match('/\(by .+\)\s*$/u', (string) $description, $by) === 1) {
                return $label.' '.$by[0];
            }

            return $label;
        }

        if (filled($description) && $description !== $action) {
            return $description;
        }

        return Str::of($action)
            ->replace(['.', '_'], ' ')
            ->squish()
            ->title()
            ->toString();
    }

    public function log(string $action, array $context = []): ?ActivityLog
    {
        try {
            return $this->record($action, $context);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function logFromRequest(Request $request, string $action, array $context = []): ?ActivityLog
    {
        return $this->log($action, array_merge($context, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));
    }

    public function record(string $action, array $context = []): ActivityLog
    {
        $user = auth('web')->user() ?? auth('team')->user();
        $request = request();

        $actorEmail = $user?->email ?? ($context['actor_email'] ?? null);
        $actorName = $context['actor_name'] ?? null;

        if ($actorName === null && $user !== null) {
            $actorName = method_exists($user, 'displayName')
                ? (string) $user->displayName()
                : (string) ($user->name ?? $user->email ?? '');
        }

        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        if ($actorName !== null && $actorName !== '') {
            $metadata['actor_name'] = $actorName;
        }

        $description = $context['description'] ?? self::labelFor($action);
        if ($actorName !== null && $actorName !== '' && ! str_contains((string) $description, $actorName)) {
            $description = $description.' (by '.$actorName.')';
        }

        return ActivityLog::query()->create([
            'uid' => Str::random(32),
            'scope' => $context['scope'] ?? $this->scopeForAction($action),
            'actor_type' => $user ? $user::class : null,
            'actor_id' => $user?->getKey(),
            'actor_email' => $actorEmail,
            'action' => $action,
            'description' => $description,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? $this->resolveClientIp($request),
            'user_agent' => $context['user_agent'] ?? $this->resolveUserAgent($request),
            'metadata' => $metadata !== [] ? $metadata : ($context['metadata'] ?? null),
            'created_at' => now(),
        ]);
    }

    private function resolveClientIp(mixed $request): ?string
    {
        if (! $request instanceof Request) {
            return null;
        }

        $ip = $request->ip();

        return filled($ip) ? (string) $ip : null;
    }

    private function resolveUserAgent(mixed $request): ?string
    {
        if (! $request instanceof Request) {
            return null;
        }

        $ua = $request->userAgent();

        return filled($ua) ? (string) $ua : null;
    }

    public function paginate(
        ?string $scope = null,
        int $perPage = 25,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = ActivityLog::query()
            ->when($scope, fn ($q) => $q->where('scope', $scope));

        \App\Domains\Admin\Support\AdminListQuery::applySort($query, $sort, $direction, [
            'created_at' => 'created_at',
            'action' => 'action',
            'scope' => 'scope',
        ], 'created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    private function scopeForAction(string $action): string
    {
        if (str_starts_with($action, 'billing.') || str_starts_with($action, 'wallet.')) {
            return 'billing';
        }

        if (str_starts_with($action, 'security.') || str_starts_with($action, 'auth.')) {
            return 'security';
        }

        return 'account';
    }
}
