<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\ActivityLog;
use App\Models\User;
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
        'data.export.requested' => 'Data export — requested',
        'data.deletion.scheduled' => 'Data deletion — scheduled',
        'data.deletion.cancelled' => 'Data deletion — cancelled',
        'data.deletion.completed' => 'Data deletion — completed',
    ];

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

        return ActivityLog::query()->create([
            'uid' => Str::random(32),
            'scope' => $context['scope'] ?? $this->scopeForAction($action),
            'actor_type' => $user ? $user::class : null,
            'actor_id' => $user?->getKey(),
            'actor_email' => $user?->email ?? ($context['actor_email'] ?? null),
            'action' => $action,
            'description' => $context['description'] ?? (self::ACTIONS[$action] ?? $action),
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function paginate(?string $scope = null, int $perPage = 25): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
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
