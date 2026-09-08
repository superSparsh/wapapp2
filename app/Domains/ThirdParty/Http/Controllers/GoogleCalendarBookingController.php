<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Models\GoogleCalendarBookingLink;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use App\Domains\ThirdParty\Services\GoogleCalendarBookingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Public booking controller — no authentication required.
 * Allows external visitors to book a Google Meet via a shared booking link.
 */
class GoogleCalendarBookingController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarApiService $api,
        private readonly GoogleCalendarBookingAvailabilityService $availability,
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
            return back()->with('error', 'Could not create the meeting. Please try again.');
        }

        return back()->with('success', 'Your meeting has been booked! Check your email for the Google Meet link.');
    }
}
