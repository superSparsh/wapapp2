<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarMessageLog;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Syncs Google Calendar events from the API into the local DB.
 * Extracted from SyncGoogleCalendarEventsJob for testability.
 *
 * Optimizations:
 * - Incremental sync via syncToken (only changed events fetched after first sync)
 * - Single-pass: all DB writes in one loop with no extra per-event queries
 * - meet_only cleanup done once after the main loop
 */
class GoogleCalendarEventSyncService
{
    public function __construct(
        private readonly GoogleCalendarApiService $api,
    ) {}

    public function sync(GoogleCalendarIntegration $integration): void
    {
        $syncToken = $integration->settings['sync_token'] ?? null;
        $result    = $this->api->listEvents($integration, $syncToken);
        $items     = $result['items'] ?? [];

        // Persist updated sync token immediately
        if (! empty($result['nextSyncToken'])) {
            $settings                = $integration->settings;
            $settings['sync_token'] = $result['nextSyncToken'];
            $integration->settings  = $settings;
            $integration->save();
        }

        $calendarId = $integration->calendarId();

        foreach ($items as $googleEvent) {
            $this->processItem($integration, $googleEvent, $calendarId);
        }

        // Meet-only: delete non-Meet events if the setting was toggled
        if ($integration->isMeetOnly()) {
            GoogleCalendarEvent::query()
                ->where('user_id', $integration->user_id)
                ->where(function ($q): void {
                    $q->whereNull('meet_link')->orWhere('meet_link', '');
                })
                ->delete();
        }

        // Mark first sync time
        if ($integration->first_synced_at === null) {
            $integration->first_synced_at = now();
            $integration->save();
        }
    }

    /**
     * @param array<string, mixed> $googleEvent
     */
    /**
     * Delete the event on Google Calendar and remove it from WapApp.
     *
     * @return array{ok: bool, message: string}
     */
    public function removeEvent(int $userId, GoogleCalendarEvent $event): array
    {
        if ((int) $event->user_id !== $userId) {
            return ['ok' => false, 'message' => 'Event not found.'];
        }

        $integration = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();
        if ($integration === null || ! $integration->hasRefreshToken()) {
            return ['ok' => false, 'message' => 'Connect Google Calendar first.'];
        }

        $platformEventId = trim((string) ($event->event_id ?? ''));
        if ($platformEventId === '') {
            return ['ok' => false, 'message' => 'Missing Google Calendar event id.'];
        }

        // Already canceled locally — still try platform delete, but don't block local cleanup.
        $deleted = $this->api->deleteEvent(
            $integration,
            $platformEventId,
            (string) ($event->calendar_id ?: $integration->calendarId()),
        );

        if (! $deleted && $event->status?->value !== 'canceled') {
            return ['ok' => false, 'message' => $this->api->lastError() ?: 'Could not delete event from Google Calendar.'];
        }

        $this->rememberRemovedEventId($integration, $platformEventId);

        GoogleCalendarMessageLog::query()
            ->where('user_id', $userId)
            ->where('event_id', $event->id)
            ->forceDelete();

        $event->forceDelete();

        return ['ok' => true, 'message' => 'Event removed from WapApp and Google Calendar.'];
    }

    public function isRemovedEventId(GoogleCalendarIntegration $integration, string $eventId): bool
    {
        $removed = $integration->settings['removed_event_ids'] ?? [];

        return is_array($removed) && in_array($eventId, $removed, true);
    }

    private function rememberRemovedEventId(GoogleCalendarIntegration $integration, string $eventId): void
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

    private function processItem(
        GoogleCalendarIntegration $integration,
        array $googleEvent,
        string $calendarId,
    ): void {
        $eventId = $googleEvent['id'] ?? null;
        if (! $eventId) {
            return;
        }

        if ($this->isRemovedEventId($integration, (string) $eventId)) {
            GoogleCalendarEvent::withTrashed()
                ->where('user_id', $integration->user_id)
                ->where('event_id', $eventId)
                ->where('calendar_id', $calendarId)
                ->forceDelete();

            return;
        }

        $times = $this->api->parseEventTimes($googleEvent);
        if (! $times['start']) {
            return;
        }

        $existing = GoogleCalendarEvent::query()
            ->where('user_id', $integration->user_id)
            ->where('event_id', $eventId)
            ->where('calendar_id', $calendarId)
            ->first();

        $status   = $this->api->mapGoogleStatus($googleEvent);
        $attendee = $this->api->extractPrimaryAttendee($googleEvent, (int) $integration->user_id);
        $desc     = $googleEvent['description'] ?? '';
        $guestName = $this->api->extractGuestNameFromDescription($desc);
        $meetLink = $this->api->extractMeetLink($googleEvent);

        // Meet-only: skip new non-Meet actives, but keep updates for already-synced / canceled rows.
        if (
            $integration->isMeetOnly()
            && $status === 'active'
            && $meetLink === null
            && $existing === null
        ) {
            return;
        }

        // Detect reschedule: active event whose start_time changed after created notification
        if ($existing && $status === 'active' && $existing->status?->value === 'active') {
            $oldStart = $existing->start_time ? Carbon::parse($existing->start_time) : null;
            if ($oldStart && ! $oldStart->equalTo($times['start']) && $existing->notified_created) {
                $status = 'rescheduled';
            }
        }

        $payload = [
            'user_id'      => $integration->user_id,
            'calendar_id'  => $calendarId,
            'status'       => $status,
            'summary'      => $googleEvent['summary'] ?? 'Meeting',
            'start_time'   => $times['start'],
            'end_time'     => $times['end'],
            'meet_link'    => $meetLink ?? $existing?->meet_link,
            'calendar_link' => $googleEvent['htmlLink'] ?? null,
            'raw_payload'  => $googleEvent,
        ];

        if ($attendee['email']) {
            $payload['invitee_email'] = $attendee['email'];
        }

        if (! empty($attendee['name'])) {
            $payload['invitee_name'] = $attendee['name'];
        } elseif ($guestName) {
            $payload['invitee_name'] = $guestName;
        }

        if ($attendee['payload']) {
            $payload['attendee_payload'] = $attendee['payload'];
        }

        $inviteeEmail = (string) ($payload['invitee_email'] ?? $existing?->invitee_email ?? '');
        $phone = $this->resolveGuestPhone(
            is_string($desc) ? $desc : '',
            $inviteeEmail !== '' ? $inviteeEmail : null,
            $existing?->whatsapp_number,
        );
        if ($phone !== null) {
            $payload['whatsapp_number'] = $phone;
        }

        if ($existing && $status === 'rescheduled') {
            $payload['previous_start_time'] = $existing->start_time;
            $payload['notified_rescheduled'] = false;
        }

        GoogleCalendarEvent::query()->updateOrCreate(
            [
                'user_id'     => $integration->user_id,
                'event_id'    => $eventId,
                'calendar_id' => $calendarId,
            ],
            $payload
        );

        $stored = GoogleCalendarEvent::query()
            ->where('user_id', $integration->user_id)
            ->where('event_id', $eventId)
            ->where('calendar_id', $calendarId)
            ->first();

        if ($stored === null || $integration->first_synced_at === null) {
            return;
        }

        if (! $integration->isWhatsAppEnabled()) {
            return;
        }

        $eventName = (string) ($stored->summary ?? 'Meeting');

        try {
            if ($status === 'canceled' && ! $stored->notified_canceled) {
                $this->notifyAndLog(
                    $integration,
                    $stored,
                    'canceled',
                    'operational-alerts.google_calendar.customer_canceled',
                    'operational-alerts.google_calendar.admin_canceled',
                    $eventName,
                );
                $stored->update(['notified_canceled' => true]);
            } elseif ($status === 'rescheduled' && ! $stored->notified_rescheduled) {
                $this->notifyAndLog(
                    $integration,
                    $stored,
                    'rescheduled',
                    'operational-alerts.google_calendar.customer_created',
                    'operational-alerts.google_calendar.admin_created',
                    $eventName,
                );
                $stored->update(['notified_rescheduled' => true, 'notified_created' => true]);
            } elseif ($status === 'active' && ! $stored->notified_created) {
                $this->notifyAndLog(
                    $integration,
                    $stored,
                    'created',
                    'operational-alerts.google_calendar.customer_created',
                    'operational-alerts.google_calendar.admin_created',
                    $eventName,
                );
                $stored->update(['notified_created' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Google Calendar booking alert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send created WhatsApp alerts immediately after a local booking / Create Meeting.
     * Sync/webhooks also call the same path; notified_created prevents duplicates.
     *
     * Always writes message logs (sent / failed / skipped) so the UI is never silent.
     *
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public function notifyForCreatedEvent(GoogleCalendarIntegration $integration, GoogleCalendarEvent $event): array
    {
        $event->refresh();
        if ($event->notified_created) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'WhatsApp already sent for this meeting.',
            ];
        }

        $eventName = (string) ($event->summary ?? 'Meeting');

        if (! $integration->isWhatsAppEnabled()) {
            $reason = 'WhatsApp notifications are disabled. Open Google Calendar → Notifications, enable WhatsApp, set admin number, then click Save.';
            $this->writeMessageLog(
                $event,
                'customer',
                $this->digitsOrNull($event->whatsapp_number),
                $eventName,
                'created',
                MessageLogStatus::Skipped,
                $reason,
            );

            $adminPhone = $integration->adminWhatsAppNumber();
            if ($adminPhone !== '') {
                $this->writeMessageLog(
                    $event,
                    'admin',
                    $this->digitsOrNull($adminPhone),
                    $eventName,
                    'created',
                    MessageLogStatus::Skipped,
                    $reason,
                );
            }

            Log::info('Google Calendar WhatsApp skipped: notifications disabled', [
                'event_id' => $event->id,
                'integration_id' => $integration->id,
            ]);

            // Do not mark notified_created — enabling WhatsApp later + Resend/sync can still send.
            return [
                'ok' => false,
                'skipped' => true,
                'message' => $reason,
            ];
        }

        try {
            $this->notifyAndLog(
                $integration,
                $event,
                'created',
                'operational-alerts.google_calendar.customer_created',
                'operational-alerts.google_calendar.admin_created',
                $eventName,
            );
            $event->update(['notified_created' => true]);

            return [
                'ok' => true,
                'skipped' => false,
                'message' => 'WhatsApp notifications sent.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Google Calendar created-event WhatsApp failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            try {
                $this->writeMessageLog(
                    $event,
                    'customer',
                    $this->digitsOrNull($event->whatsapp_number),
                    $eventName,
                    'created',
                    MessageLogStatus::Failed,
                    $e->getMessage(),
                );
            } catch (\Throwable) {
                // non-fatal
            }

            return [
                'ok' => false,
                'skipped' => false,
                'message' => 'WhatsApp send failed: '.$e->getMessage(),
            ];
        }
    }

    private function digitsOrNull(mixed $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($phone ?? '')) ?: '';

        return $digits !== '' ? $digits : null;
    }

    public function templateKeyFor(string $recipientType, string $eventType): ?string
    {
        $recipientType = strtolower(trim($recipientType));
        $eventType = strtolower(trim($eventType));

        return match (true) {
            $recipientType === 'customer' && $eventType === 'created' => 'operational-alerts.google_calendar.customer_created',
            $recipientType === 'customer' && $eventType === 'canceled' => 'operational-alerts.google_calendar.customer_canceled',
            $recipientType === 'customer' && $eventType === 'rescheduled' => 'operational-alerts.google_calendar.customer_created',
            $recipientType === 'customer' && $eventType === 'reminder' => 'operational-alerts.google_calendar.customer_reminder',
            $recipientType === 'admin' && $eventType === 'created' => 'operational-alerts.google_calendar.admin_created',
            $recipientType === 'admin' && $eventType === 'canceled' => 'operational-alerts.google_calendar.admin_canceled',
            $recipientType === 'admin' && $eventType === 'rescheduled' => 'operational-alerts.google_calendar.admin_created',
            $recipientType === 'admin' && $eventType === 'reminder' => 'operational-alerts.google_calendar.admin_reminder',
            default => null,
        };
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resendMessageLog(int $userId, GoogleCalendarMessageLog $log): array
    {
        if ((int) $log->user_id !== $userId) {
            return ['ok' => false, 'message' => 'Message log not found.'];
        }

        if ($log->status !== MessageLogStatus::Failed) {
            return ['ok' => false, 'message' => 'Only failed messages can be resent.'];
        }

        $phone = trim((string) ($log->recipient_number ?? ''));
        if ($phone === '') {
            return ['ok' => false, 'message' => 'No recipient number on this log.'];
        }

        $templateKey = $this->templateKeyFor(
            (string) ($log->recipient_type ?? ''),
            (string) ($log->event_type ?? ''),
        );
        if ($templateKey === null) {
            return ['ok' => false, 'message' => 'Cannot determine WhatsApp template for this log.'];
        }

        $event = $log->event;
        if ($event === null && $log->event_id) {
            $event = GoogleCalendarEvent::query()->find($log->event_id);
        }
        if ($event === null) {
            return ['ok' => false, 'message' => 'Linked Google Calendar event is missing.'];
        }

        $eventName = trim((string) ($log->event_name ?? ''));
        $params = $this->templateParamsForEvent(
            $event,
            $eventName !== '' ? $eventName : null,
            forAdmin: strtolower((string) ($log->recipient_type ?? '')) === 'admin',
        );

        $result = app(AlertDispatcher::class)->calendarWhatsApp($templateKey, $phone, $params);
        if ($result['ok']) {
            $messageId = is_string($result['message_id'] ?? null) ? trim((string) $result['message_id']) : '';
            $this->indexProviderMessageId($messageId);
            $log->forceFill([
                'status' => MessageLogStatus::Sent,
                'external_message_id' => $messageId !== '' ? $messageId : $log->external_message_id,
                'error_message' => null,
                'sent_at' => now(),
            ])->save();

            return ['ok' => true, 'message' => 'WhatsApp message resent successfully.'];
        }

        $error = $result['error'] ?: 'WhatsApp API did not return success.';
        $log->forceFill([
            'status' => MessageLogStatus::Failed,
            'error_message' => $error,
            'sent_at' => now(),
        ])->save();

        return ['ok' => false, 'message' => $error];
    }

    /**
     * Legacy CAMS template variables (SendsGoogleCalendarWhatsapp).
     *
     * @return array<string, string>
     */
    public function templateParamsForEvent(GoogleCalendarEvent $event, ?string $eventName = null, bool $forAdmin = false): array
    {
        $tz = (string) (tenant('timezone') ?: config('app.timezone', 'UTC'));
        $eventName = $this->fallbackNa($eventName ?? (string) ($event->summary ?? 'Meeting'), 'Meeting');
        $clientName = $this->fallbackNa(
            (string) ($event->invitee_name ?? $event->summary ?? 'Guest'),
            'Guest',
        );

        $params = [
            'full_name' => $clientName,
            'google_event_name' => $eventName,
            'google_event_start_time' => $this->formatEventTime($event->start_time, $tz),
            'google_event_end_time' => $this->formatEventTime($event->end_time, $tz),
            'google_meet_link' => $this->fallbackNa((string) ($event->meet_link ?? '')),
            'google_calendar_link' => $this->fallbackNa((string) ($event->calendar_link ?? '')),
        ];

        if ($forAdmin) {
            $params['whatsapp_number'] = $this->fallbackNa((string) ($event->whatsapp_number ?? ''));
            $params['invitee_email'] = $this->fallbackNa((string) ($event->invitee_email ?? ''));
        }

        return $params;
    }

    /**
     * Send customer + admin WhatsApp notifications and write message logs (sent / failed / skipped).
     */
    private function notifyAndLog(
        GoogleCalendarIntegration $integration,
        GoogleCalendarEvent $event,
        string $eventType,
        string $customerTemplateKey,
        string $adminTemplateKey,
        string $eventName,
    ): void {
        $dispatcher = app(AlertDispatcher::class);
        $phone = (string) ($event->whatsapp_number ?? '');
        $customerParams = $this->templateParamsForEvent($event, $eventName, forAdmin: false);

        if ($phone === '') {
            $this->writeMessageLog($event, 'customer', null, $eventName, $eventType, MessageLogStatus::Skipped, 'No WhatsApp number on the event. Add a phone in the event description or when creating/booking the meeting.');
        } else {
            $digits = preg_replace('/\D/', '', $phone) ?: '';
            if (strlen($digits) < 10) {
                $this->writeMessageLog($event, 'customer', $digits, $eventName, $eventType, MessageLogStatus::Skipped, 'Phone number has fewer than 10 digits.');
            } else {
                try {
                    $result = $dispatcher->calendarWhatsApp($customerTemplateKey, $phone, $customerParams);
                    $messageId = is_string($result['message_id'] ?? null) ? trim((string) $result['message_id']) : '';
                    if ($result['ok'] && $messageId !== '') {
                        $this->indexProviderMessageId($messageId);
                    }
                    $this->writeMessageLog(
                        $event,
                        'customer',
                        $digits,
                        $eventName,
                        $eventType,
                        $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                        $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
                        $result['ok'] && $messageId !== '' ? $messageId : null,
                    );
                } catch (\Throwable $e) {
                    $this->writeMessageLog(
                        $event,
                        'customer',
                        $digits,
                        $eventName,
                        $eventType,
                        MessageLogStatus::Failed,
                        $e->getMessage(),
                    );
                }
            }
        }

        $adminPhone = $integration->adminWhatsAppNumber();
        if ($adminPhone === '') {
            $this->writeMessageLog(
                $event,
                'admin',
                null,
                $eventName,
                $eventType,
                MessageLogStatus::Skipped,
                'No admin WhatsApp number set. Add it under Google Calendar → Notifications.',
            );

            return;
        }

        $adminDigits = preg_replace('/\D/', '', $adminPhone) ?: '';
        if (strlen($adminDigits) < 10) {
            $this->writeMessageLog($event, 'admin', $adminDigits, $eventName, $eventType, MessageLogStatus::Skipped, 'Admin WhatsApp number has fewer than 10 digits.');

            return;
        }

        $adminParams = $this->templateParamsForEvent($event, $eventName, forAdmin: true);
        try {
            $result = $dispatcher->calendarWhatsApp($adminTemplateKey, $adminPhone, $adminParams);
            $messageId = is_string($result['message_id'] ?? null) ? trim((string) $result['message_id']) : '';
            if ($result['ok'] && $messageId !== '') {
                $this->indexProviderMessageId($messageId);
            }
            $this->writeMessageLog(
                $event,
                'admin',
                $adminDigits,
                $eventName,
                $eventType,
                $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
                $result['ok'] && $messageId !== '' ? $messageId : null,
            );
        } catch (\Throwable $e) {
            $this->writeMessageLog(
                $event,
                'admin',
                $adminDigits,
                $eventName,
                $eventType,
                MessageLogStatus::Failed,
                $e->getMessage(),
            );
        }
    }

    private function indexProviderMessageId(string $messageId): void
    {
        $messageId = trim($messageId);
        $tenantId = (string) (tenant('id') ?? '');
        if ($messageId === '' || $tenantId === '') {
            return;
        }

        try {
            app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
                ->indexMessage($tenantId, $messageId);
        } catch (\Throwable $e) {
            Log::warning('Failed to index Google Calendar WhatsApp message id', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatEventTime(mixed $time, string $timezone): string
    {
        if (! $time instanceof \DateTimeInterface) {
            return 'N/A';
        }

        try {
            return Carbon::instance($time)->timezone($timezone)->format('d M Y, h:i A');
        } catch (\Throwable) {
            return Carbon::instance($time)->format('d M Y, h:i A');
        }
    }

    private function fallbackNa(string $value, string $fallback = 'N/A'): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    /**
     * Prefer Phone/WhatsApp in the event description, then audience contact by invitee email
     * (legacy Google Calendar behavior), then any previously stored number.
     */
    private function resolveGuestPhone(string $description, ?string $inviteeEmail, mixed $existingPhone): ?string
    {
        $fromDescription = $this->api->extractPhoneFromDescription($description);
        if (is_string($fromDescription) && $fromDescription !== '') {
            return preg_replace('/\s+/', '', $fromDescription) ?: $fromDescription;
        }

        $email = strtolower(trim((string) $inviteeEmail));
        if ($email !== '') {
            $contactPhone = Contact::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNotNull('phone')
                ->value('phone');

            $contactPhone = trim((string) $contactPhone);
            if ($contactPhone !== '') {
                return preg_replace('/\s+/', '', $contactPhone) ?: $contactPhone;
            }
        }

        $existing = trim((string) ($existingPhone ?? ''));
        if ($existing !== '') {
            return preg_replace('/\s+/', '', $existing) ?: $existing;
        }

        return null;
    }

    private function writeMessageLog(
        GoogleCalendarEvent $event,
        string $recipientType,
        ?string $recipientNumber,
        string $eventName,
        string $eventType,
        MessageLogStatus $status,
        ?string $errorMessage,
        ?string $externalMessageId = null,
    ): void {
        $payload = [
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'recipient_type' => $recipientType,
            'recipient_number' => $recipientNumber,
            'invitee_email' => $event->invitee_email,
            'event_name' => $eventName,
            'event_type' => $eventType,
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ];

        // Defensive: older tenant DBs may not have run the delivery-fields migration yet.
        if (\Illuminate\Support\Facades\Schema::hasColumn('google_calendar_message_logs', 'external_message_id')) {
            $payload['external_message_id'] = $externalMessageId;
        }

        GoogleCalendarMessageLog::query()->create($payload);
    }
}
