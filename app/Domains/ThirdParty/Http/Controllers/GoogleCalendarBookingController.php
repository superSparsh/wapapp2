<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Jobs\SyncGoogleCalendarEventsJob;
use App\Domains\ThirdParty\Models\GoogleCalendarBookingLink;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use App\Domains\ThirdParty\Services\GoogleCalendarBookingAvailabilityService;
use App\Domains\ThirdParty\Services\GoogleCalendarEventSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Public booking controller - no authentication required.
 * Allows external visitors to book a Google Meet via a shared booking link.
 */
class GoogleCalendarBookingController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarApiService $api,
        private readonly GoogleCalendarBookingAvailabilityService $availability,
        private readonly GoogleCalendarEventSyncService $sync,
    ) {}

    public function show(string $slug): View
    {
        $link = GoogleCalendarBookingLink::query()
            ->where('slug', $slug)
            ->where('status', \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled)
            ->firstOrFail();

        return view('integration.google-calendar.booking', compact('link'));
    }

    public function availability(string $slug, Request $request): JsonResponse
    {
        $link = GoogleCalendarBookingLink::query()
            ->where('slug', $slug)
            ->where('status', \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled)
            ->firstOrFail();

        $dateStr = (string) $request->get('date', today()->toDateString());
        $date    = Carbon::parse($dateStr);

        $integration = GoogleCalendarIntegration::query()
            ->where('user_id', $link->user_id)
            ->first();

        if (! $integration || ! $integration->hasRefreshToken()) {
            return response()->json(['slots' => []]);
        }

        $slots = $this->availability->getAvailableSlots($integration, $this->api, $date, (int) $link->duration_minutes);

        return response()->json(['slots' => $slots]);
    }

    public function book(string $slug, Request $request): RedirectResponse|JsonResponse
    {
        $link = GoogleCalendarBookingLink::query()
            ->where('slug', $slug)
            ->where('status', \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled)
            ->firstOrFail();

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['nullable', 'email', 'max:255'],
            'phone'     => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'start_at'  => ['required', 'date', 'after:now'],
        ]);

        $integration = GoogleCalendarIntegration::query()
            ->where('user_id', $link->user_id)
            ->first();

        if (! $integration || ! $integration->hasRefreshToken()) {
            return back()->with('error', 'This booking link is currently unavailable.');
        }

        $start = Carbon::parse($validated['start_at']);
        $end   = $start->copy()->addMinutes((int) $link->duration_minutes);

        // Verify slot is still available
        if ($this->api->hasOverlappingEvent($integration, $start, $end)) {
            return back()->with('error', 'This time slot is no longer available. Please choose another.');
        }

        $googleEvent = $this->api->createEventWithMeet(
            $integration,
            $link->title,
            $start,
            $end,
            $validated['email'] ?? null,
            $validated['name'],
            $validated['phone'],
            null,
        );

        if (! $googleEvent) {
            return back()->with(
                'error',
                $this->api->lastError() ?: 'Could not create the meeting. Please try again.',
            );
        }

        $stored = $this->persistBookedEvent(
            $integration,
            $googleEvent,
            $validated['name'],
            $validated['email'] ?? null,
            $validated['phone'],
            $start,
            $end,
        );

        $notify = null;
        if ($stored !== null) {
            // Send WhatsApp immediately — do not wait for background sync.
            $notify = $this->sync->notifyForCreatedEvent($integration, $stored);
        }

        $tenantId = tenancy()->tenant?->getTenantKey();
        SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);

        $meetLink = $this->api->extractMeetLink($googleEvent);
        $success = $meetLink
            ? 'Your meeting has been booked! Google Meet: '.$meetLink
            : 'Your meeting has been booked on Google Calendar. The Meet link will appear shortly.';

        $redirect = back()->with('success', $success);

        if (is_array($notify) && ($notify['skipped'] ?? false) && ! ($notify['ok'] ?? false)) {
            $redirect->with('error', $notify['message']);
        } elseif (is_array($notify) && ! ($notify['ok'] ?? true) && ! ($notify['skipped'] ?? false)) {
            $redirect->with('error', $notify['message']);
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $googleEvent
     */
    private function persistBookedEvent(
        GoogleCalendarIntegration $integration,
        array $googleEvent,
        string $guestName,
        ?string $guestEmail,
        string $guestPhone,
        Carbon $start,
        Carbon $end,
    ): ?GoogleCalendarEvent {
        $eventId = (string) ($googleEvent['id'] ?? '');
        if ($eventId === '') {
            return null;
        }

        $times = $this->api->parseEventTimes($googleEvent);

        return GoogleCalendarEvent::query()->updateOrCreate(
            [
                'user_id' => $integration->user_id,
                'event_id' => $eventId,
                'calendar_id' => $integration->calendarId(),
            ],
            [
                'status' => 'active',
                'summary' => (string) ($googleEvent['summary'] ?? 'Meeting'),
                'start_time' => $times['start'] ?? $start,
                'end_time' => $times['end'] ?? $end,
                'meet_link' => $this->api->extractMeetLink($googleEvent),
                'calendar_link' => $googleEvent['htmlLink'] ?? null,
                'invitee_email' => $guestEmail,
                'invitee_name' => $guestName,
                'whatsapp_number' => preg_replace('/\s+/', '', $guestPhone) ?: $guestPhone,
                'raw_payload' => $googleEvent,
                'notified_created' => false,
            ],
        );
    }
}
