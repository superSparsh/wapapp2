<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Jobs\SyncCalendlyEventsJob;
use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
use App\Domains\ThirdParty\Models\CalendlyMessageLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CalendlyService
{
    /**
     * Get or create the integration record for a user.
     */
    public function findOrCreate(int $userId): CalendlyIntegration
    {
        /** @var CalendlyIntegration */
        return CalendlyIntegration::query()->firstOrCreate(
            ['user_id' => $userId],
            ['status' => IntegrationStatus::Disabled, 'settings' => []]
        );
    }

    /**
     * Validate a Calendly personal access token against the Calendly API.
     */
    public function validateToken(string $token): bool
    {
        $response = Http::withToken($token)
            ->timeout(10)
            ->get('https://api.calendly.com/users/me');

        return $response->successful();
    }

    /**
     * Fetch user profile from Calendly API.
     *
     * @return array<string, mixed>|null
     */
    public function fetchUserProfile(string $token): ?array
    {
        $response = Http::withToken($token)
            ->timeout(10)
            ->get('https://api.calendly.com/users/me');

        return $response->successful() ? ($response->json('resource') ?? null) : null;
    }

    /**
     * Persist integration settings. Handles webhook registration on token change.
     *
     * @param array<string, mixed> $newSettings
     */
    public function saveSettings(
        CalendlyIntegration $integration,
        array $newSettings,
        bool $tokenChanged,
    ): CalendlyIntegration {
        if ($tokenChanged && ($newSettings['access_token'] ?? null)) {
            $this->ensureWebhook((string) $newSettings['access_token'], $newSettings);

            // Fetch user / org URIs
            $profile = $this->fetchUserProfile((string) $newSettings['access_token']);
            if ($profile) {
                $newSettings['user_uri']          = $profile['uri'] ?? null;
                $newSettings['organization_uri']  = $profile['current_organization'] ?? null;
                $newSettings['user_email']        = $profile['email'] ?? $newSettings['user_email'] ?? null;
            }
        }

        $integration->settings = $newSettings;

        if ($integration->status !== IntegrationStatus::Enabled) {
            $integration->status = IntegrationStatus::Enabled;
        }

        $integration->save();

        return $integration;
    }

    /**
     * Toggle enabled ↔ disabled, deleting events on disable.
     */
    public function toggle(CalendlyIntegration $integration): void
    {
        if ($integration->isEnabled()) {
            CalendlyEvent::query()
                ->where('user_id', $integration->user_id)
                ->forceDelete();
            $integration->first_synced_at = null;
        }

        $integration->status = $integration->status->toggle();
        $integration->save();
    }

    /**
     * Dispatch an async sync job for this integration.
     */
    public function dispatchSync(CalendlyIntegration $integration, string $tenantId): void
    {
        SyncCalendlyEventsJob::dispatch($integration->id, $tenantId);
    }

    /**
     * Paginated events for a user, optionally filtered by status.
     */
    public function events(int $userId, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        return CalendlyEvent::query()
            ->where('user_id', $userId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'canceled' THEN 1 ELSE 2 END")
            ->latest('start_time')
            ->paginate($perPage);
    }

    /**
     * Paginated message logs for a user, optionally filtered by status.
     */
    public function messageLogs(int $userId, ?string $logType, int $perPage = 15): LengthAwarePaginator
    {
        return CalendlyMessageLog::query()
            ->where('user_id', $userId)
            ->when($logType, fn ($q) => $q->where('status', $logType))
            ->latest('sent_at')
            ->paginate($perPage, ['*'], 'logs_page');
    }

    /**
     * Register or replace the Calendly org-scoped webhook subscription.
     *
     * @param array<string, mixed> &$settings  Mutated in-place (webhook_id, signing_key written back)
     */
    public function ensureWebhook(string $token, array &$settings): void
    {
        try {
            $webhookUrl = route('calendly.webhook');

            $userResp = Http::withToken($token)->timeout(10)->get('https://api.calendly.com/users/me');
            if (! $userResp->successful()) {
                return;
            }

            $user   = $userResp->json('resource');
            $orgUri = $user['current_organization'] ?? null;
            if (! $orgUri) {
                return;
            }

            // Remove any pre-existing webhook pointing to our URL
            $listResp = Http::withToken($token)->timeout(10)->get('https://api.calendly.com/webhook_subscriptions', [
                'organization' => $orgUri,
                'scope'        => 'organization',
            ]);

            foreach (($listResp->json('collection') ?? []) as $hook) {
                if (($hook['callback_url'] ?? '') === $webhookUrl) {
                    Http::withToken($token)->timeout(10)->delete($hook['uri']);
                }
            }

            $signingKey = Str::random(32);

            $createResp = Http::withToken($token)->timeout(10)->post('https://api.calendly.com/webhook_subscriptions', [
                'url'          => $webhookUrl,
                'events'       => ['invitee.created', 'invitee.canceled'],
                'scope'        => 'organization',
                'organization' => $orgUri,
                'signing_key'  => $signingKey,
            ]);

            if ($createResp->successful()) {
                $settings['webhook_id']   = $createResp->json('resource.id');
                $settings['signing_key']  = $signingKey;
            }
        } catch (\Throwable) {
            // Non-fatal: webhook registration should not block token save
        }
    }
}
