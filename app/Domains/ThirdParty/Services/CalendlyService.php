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
     * Cancel the event on Calendly (when possible) and remove it from WapApp.
     *
     * @return array{ok: bool, message: string}
     */
    public function removeEvent(int $userId, CalendlyEvent $event): array
    {
        if ((int) $event->user_id !== $userId) {
            return ['ok' => false, 'message' => 'Event not found.'];
        }

        $integration = $this->findOrCreate($userId);
        $token = trim((string) ($integration->settings['access_token'] ?? ''));
        $platformError = null;

        if ($token !== '' && ! $event->isCanceled()) {
            $uuid = $this->calendlyScheduledEventUuid($event);
            if ($uuid === null) {
                $platformError = 'Could not resolve Calendly event id for cancellation.';
            } else {
                $response = Http::withToken($token)
                    ->timeout(20)
                    ->post('https://api.calendly.com/scheduled_events/'.$uuid.'/cancellation', [
                        'reason' => 'Canceled from WapApp',
                    ]);

                // 201/200 = canceled. 403/404 often means already canceled / gone — still remove locally.
                if (! $response->successful() && ! in_array($response->status(), [403, 404], true)) {
                    $platformError = (string) (
                        $response->json('message')
                        ?? $response->json('title')
                        ?? ('Calendly cancel failed (HTTP '.$response->status().').')
                    );
                }
            }
        }

        if ($platformError !== null) {
            return ['ok' => false, 'message' => $platformError];
        }

        $this->rememberRemovedEventId($integration, (string) $event->event_id);

        CalendlyMessageLog::query()
            ->where('user_id', $userId)
            ->where('event_id', $event->id)
            ->forceDelete();

        $event->forceDelete();

        return ['ok' => true, 'message' => 'Event removed from WapApp and Calendly.'];
    }

    public function isRemovedEventId(CalendlyIntegration $integration, string $eventId): bool
    {
        $removed = $integration->settings['removed_event_ids'] ?? [];

        return is_array($removed) && in_array($eventId, $removed, true);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function rememberRemovedEventId(CalendlyIntegration $integration, string $eventId): void
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return;
        }

        $settings = $integration->settings ?? [];
        $existing = is_array($settings['removed_event_ids'] ?? null)
            ? $settings['removed_event_ids']
            : [];
        $settings['removed_event_ids'] = array_values(array_slice(
            array_unique([...$existing, $eventId]),
            -500,
        ));
        $integration->settings = $settings;
        $integration->save();
    }

    private function calendlyScheduledEventUuid(CalendlyEvent $event): ?string
    {
        foreach ([$event->event_uri, $event->event_id, $event->raw_payload['uri'] ?? null] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (preg_match('~/scheduled_events/([^/?]+)$~i', $candidate, $matches) === 1) {
                return $matches[1];
            }
            if (preg_match('/^[a-f0-9-]{36}$/i', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resend a failed Calendly WhatsApp notification.
     *
     * @return array{ok: bool, message: string}
     */
    public function resendMessageLog(int $userId, CalendlyMessageLog $log): array
    {
        if ((int) $log->user_id !== $userId) {
            return ['ok' => false, 'message' => 'Message log not found.'];
        }

        if ($log->status !== \App\Domains\ThirdParty\Enums\MessageLogStatus::Failed) {
            return ['ok' => false, 'message' => 'Only failed messages can be resent.'];
        }

        $phone = trim((string) ($log->recipient_number ?? ''));
        if ($phone === '') {
            return ['ok' => false, 'message' => 'No recipient number on this log.'];
        }

        $sync = app(CalendlyEventSyncService::class);
        $templateKey = $sync->templateKeyFor(
            (string) ($log->recipient_type ?? ''),
            (string) ($log->event_type ?? ''),
        );

        if ($templateKey === null) {
            return ['ok' => false, 'message' => 'Cannot determine WhatsApp template for this log.'];
        }

        $event = $log->event;
        if ($event === null && $log->event_id) {
            $event = CalendlyEvent::query()->find($log->event_id);
        }

        if ($event === null) {
            return ['ok' => false, 'message' => 'Linked Calendly event is missing.'];
        }

        $eventName = trim((string) ($log->event_name ?? ''));
        $params = $sync->templateParamsForEvent(
            $event,
            $eventName !== '' ? $eventName : null,
            forAdmin: strtolower((string) ($log->recipient_type ?? '')) === 'admin',
        );
        $result = app(\App\Domains\Alerts\Services\AlertDispatcher::class)
            ->calendarWhatsApp($templateKey, $phone, $params);

        if ($result['ok']) {
            $messageId = is_string($result['message_id'] ?? null) ? trim((string) $result['message_id']) : '';
            if ($messageId !== '') {
                try {
                    app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
                        ->indexMessage((string) (tenant('id') ?? ''), $messageId);
                } catch (\Throwable) {
                    // non-fatal
                }
            }
            $log->forceFill([
                'status' => \App\Domains\ThirdParty\Enums\MessageLogStatus::Sent,
                'external_message_id' => $messageId !== '' ? $messageId : $log->external_message_id,
                'error_message' => null,
                'sent_at' => now(),
            ])->save();

            return ['ok' => true, 'message' => 'WhatsApp message resent successfully.'];
        }

        $error = $result['error'] ?: 'WhatsApp API did not return success.';
        $log->forceFill([
            'status' => \App\Domains\ThirdParty\Enums\MessageLogStatus::Failed,
            'error_message' => $error,
            'sent_at' => now(),
        ])->save();

        return ['ok' => false, 'message' => $error];
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
