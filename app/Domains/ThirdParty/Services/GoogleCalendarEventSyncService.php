<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarMessageLog;
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
    private function processItem(
        GoogleCalendarIntegration $integration,
        array $googleEvent,
        string $calendarId,
    ): void {
        $eventId = $googleEvent['id'] ?? null;
        if (! $eventId) {
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
        $phone    = $this->api->extractPhoneFromDescription($desc);
        $guestName = $this->api->extractGuestNameFromDescription($desc);

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
            'meet_link'    => $this->api->extractMeetLink($googleEvent),
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

        if ($phone) {
            $payload['whatsapp_number'] = preg_replace('/\s+/', '', $phone);
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

        $enableWhatsapp = (bool) ($integration->settings['enable_whatsapp'] ?? false);
        if (! $enableWhatsapp) {
            return;
        }

        $eventName = (string) ($stored->summary ?? 'Meeting');
        $params = [
            'name'  => (string) ($stored->invitee_name ?? $stored->summary ?? 'Guest'),
            'event' => $eventName,
            'start' => $stored->start_time?->format('d M Y h:i A') ?? '',
            'meet'  => (string) ($stored->meet_link ?? ''),
            'link'  => (string) ($stored->meet_link ?: $stored->calendar_link ?: ''),
        ];

        try {
            if ($status === 'canceled' && ! $stored->notified_canceled) {
                $this->notifyAndLog(
                    $integration,
                    $stored,
                    'canceled',
                    'operational-alerts.google_calendar.customer_canceled',
                    'operational-alerts.google_calendar.admin_canceled',
                    $params,
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
                    $params,
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
                    $params,
                    $eventName,
                );
                $stored->update(['notified_created' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Google Calendar booking alert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send customer + admin WhatsApp notifications and write message logs (sent / failed / skipped).
     *
     * @param  array<string, string>  $params
     */
    private function notifyAndLog(
        GoogleCalendarIntegration $integration,
        GoogleCalendarEvent $event,
        string $eventType,
        string $customerTemplateKey,
        string $adminTemplateKey,
        array $params,
        string $eventName,
    ): void {
        $dispatcher = app(AlertDispatcher::class);
        $phone = (string) ($event->whatsapp_number ?? '');

        if ($phone === '') {
            $this->writeMessageLog($event, 'customer', null, $eventName, $eventType, MessageLogStatus::Skipped, 'No WhatsApp number on the event. Add a phone in the event description or when creating/booking the meeting.');
        } else {
            $digits = preg_replace('/\D/', '', $phone) ?: '';
            if (strlen($digits) < 10) {
                $this->writeMessageLog($event, 'customer', $digits, $eventName, $eventType, MessageLogStatus::Skipped, 'Phone number has fewer than 10 digits.');
            } else {
                $ok = $dispatcher->calendarWhatsApp($customerTemplateKey, $phone, $params);
                $this->writeMessageLog(
                    $event,
                    'customer',
                    $digits,
                    $eventName,
                    $eventType,
                    $ok ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                    $ok ? null : 'WhatsApp API did not return success.',
                );
            }
        }

        $adminPhone = (string) ($integration->settings['whatsapp_number'] ?? '');
        if ($adminPhone === '') {
            return;
        }

        $adminDigits = preg_replace('/\D/', '', $adminPhone) ?: '';
        if (strlen($adminDigits) < 10) {
            $this->writeMessageLog($event, 'admin', $adminDigits, $eventName, $eventType, MessageLogStatus::Skipped, 'Admin WhatsApp number has fewer than 10 digits.');

            return;
        }

        $ok = $dispatcher->calendarWhatsApp($adminTemplateKey, $adminPhone, $params);
        $this->writeMessageLog(
            $event,
            'admin',
            $adminDigits,
            $eventName,
            $eventType,
            $ok ? MessageLogStatus::Sent : MessageLogStatus::Failed,
            $ok ? null : 'WhatsApp API did not return success.',
        );
    }

    private function writeMessageLog(
        GoogleCalendarEvent $event,
        string $recipientType,
        ?string $recipientNumber,
        string $eventName,
        string $eventType,
        MessageLogStatus $status,
        ?string $errorMessage,
    ): void {
        GoogleCalendarMessageLog::query()->create([
            'user_id'          => $event->user_id,
            'event_id'         => $event->id,
            'recipient_type'   => $recipientType,
            'recipient_number' => $recipientNumber,
            'invitee_email'    => $event->invitee_email,
            'event_name'       => $eventName,
            'event_type'       => $eventType,
            'status'           => $status,
            'error_message'    => $errorMessage,
            'sent_at'          => now(),
        ]);
    }
}
