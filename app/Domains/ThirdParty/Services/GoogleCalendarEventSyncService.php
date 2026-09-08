<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
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
        $customerId = null; // No customer_id concept in WapApp 2.0; phone lookup via description only

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
    }
}
