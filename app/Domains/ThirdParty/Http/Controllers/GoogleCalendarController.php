<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Http\Requests\GoogleCalendar\CreateMeetingRequest;
use App\Domains\ThirdParty\Http\Requests\GoogleCalendar\StoreBookingLinkRequest;
use App\Domains\ThirdParty\Http\Requests\GoogleCalendar\UpdateBookingAvailabilityRequest;
use App\Domains\ThirdParty\Http\Requests\GoogleCalendar\UpdateGoogleCalendarRequest;
use App\Domains\ThirdParty\Jobs\SyncGoogleCalendarEventsJob;
use App\Domains\ThirdParty\Models\GoogleCalendarBookingLink;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarMessageLog;
use App\Domains\ThirdParty\Models\GoogleCalendarWebhookLog;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use App\Domains\ThirdParty\Services\GoogleCalendarBookingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarApiService $api,
        private readonly GoogleCalendarBookingAvailabilityService $availability,
    ) {}

    public function index(Request $request): View
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->firstOrCreate(
            ['user_id' => $userId],
            ['status' => \App\Domains\ThirdParty\Enums\IntegrationStatus::Disabled, 'settings' => []]
        );

        $connected = $integration->isEnabled() && $integration->hasRefreshToken()
            && $this->api->testConnection($integration);

        $nowUtc  = Carbon::now('UTC');
        $status  = $request->get('status');
        $logType = $request->get('log_type');

        $events = GoogleCalendarEvent::query()
            ->where('user_id', $userId)
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'rescheduled' THEN 1 WHEN 'canceled' THEN 2 ELSE 3 END")
            ->when($status, function ($q) use ($status, $nowUtc): void {
                if ($status === 'upcoming') {
                    $q->where('status', 'active')->where(fn ($q2) => $q2
                        ->where(fn ($q3) => $q3->whereNotNull('end_time')->where('end_time', '>', $nowUtc))
                        ->orWhere(fn ($q3) => $q3->whereNull('end_time')->where('start_time', '>', $nowUtc))
                    );
                } elseif ($status === 'completed') {
                    $q->where('status', 'active')->where(fn ($q2) => $q2
                        ->where(fn ($q3) => $q3->whereNotNull('end_time')->where('end_time', '<=', $nowUtc))
                        ->orWhere(fn ($q3) => $q3->whereNull('end_time')->where('start_time', '<=', $nowUtc))
                    );
                } else {
                    $q->where('status', $status);
                }
            })
            ->latest('start_time')
            ->paginate(10);

        // Auto-detect active tab
        $activeTab = $request->get('tab');
        if ($activeTab === null && ($request->has('page') || $request->has('status'))) {
            $activeTab = 'events';
        }
        if ($activeTab === null && ($request->has('logs_page') || $request->has('log_type'))) {
            $activeTab = 'logs';
        }
        if ($activeTab === null && $request->has('booking_page')) {
            $activeTab = 'booking';
        }
        $activeTab ??= 'connect';

        $messageLogs = GoogleCalendarMessageLog::query()
            ->where('user_id', $userId)
            ->when($logType, fn ($q) => $q->where('status', $logType))
            ->latest('sent_at')
            ->paginate(15, ['*'], 'logs_page');

        $calendars   = $connected ? $this->api->listCalendars($integration) : [];
        $bookingLinks = GoogleCalendarBookingLink::query()->where('user_id', $userId)->latest()->get();
        $bookingAvailability = $this->availability->resolveFromIntegration($integration);

        $reminderOptions = [15, 30, 45, 60, 90, 120, 180, 240, 360, 720, 1440];

        return view('integration.google-calendar', compact(
            'integration',
            'connected',
            'events',
            'activeTab',
            'messageLogs',
            'calendars',
            'bookingLinks',
            'bookingAvailability',
            'reminderOptions',
        ) + [
            'oauthConfigured'  => GoogleCalendarApiService::isOAuthConfigured(),
            'oauthRedirectUri' => $this->api->oauthRedirectUrl(),
        ]);
    }

    public function toggle(): RedirectResponse
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->firstOrCreate(['user_id' => $userId]);

        if ($integration->isEnabled()) {
            $this->api->stopWatch($integration);
            GoogleCalendarEvent::query()->where('user_id', $userId)->forceDelete();
            $integration->first_synced_at = null;
            $settings = $integration->settings ?? [];
            unset($settings['sync_token']);
            $integration->settings = $settings;
        }

        $integration->status = $integration->status?->toggle()
            ?? \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled;
        $integration->save();

        if ($integration->isEnabled() && $integration->hasRefreshToken()) {
            $this->api->registerWatch($integration);
            $tenantId = tenancy()->tenant?->getTenantKey();
            SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);
        }

        return redirect()->route('integration.google-calendar')
            ->with('status', 'Plugin status updated.');
    }

    public function update(UpdateGoogleCalendarRequest $request): RedirectResponse
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->firstOrCreate(['user_id' => $userId]);
        $old         = $integration->settings ?? [];
        $input       = $request->validated()['settings'] ?? [];

        $reminderMinutes = isset($input['reminder_minutes_before'])
            ? (int) $input['reminder_minutes_before']
            : ($old['reminder_minutes_before'] ?? 30);

        $newSettings = array_merge($old, [
            'whatsapp_number'         => $input['whatsapp_number'] ?? $old['whatsapp_number'] ?? null,
            'user_email'              => $input['user_email'] ?? $old['user_email'] ?? null,
            'enable_whatsapp'         => isset($input['enable_whatsapp']),
            'calendar_id'             => $input['calendar_id'] ?? $old['calendar_id'] ?? 'primary',
            'meet_only'               => isset($input['meet_only']),
            'reminder_minutes_before' => $reminderMinutes,
        ]);

        $calendarChanged = ($old['calendar_id'] ?? 'primary') !== ($newSettings['calendar_id'] ?? 'primary');
        $meetOnlyEnabled = ! empty($newSettings['meet_only']) && empty($old['meet_only']);

        if ($calendarChanged || $meetOnlyEnabled) {
            unset($newSettings['sync_token']);
        }

        $integration->settings = $newSettings;
        $integration->save();

        $removedNonMeet = 0;
        if (! empty($newSettings['meet_only'])) {
            $removedNonMeet = GoogleCalendarEvent::query()
                ->where('user_id', $userId)
                ->where(fn ($q) => $q->whereNull('meet_link')->orWhere('meet_link', ''))
                ->delete();
        }

        if ($integration->isEnabled() && ($calendarChanged || $meetOnlyEnabled)) {
            $tenantId = tenancy()->tenant?->getTenantKey();
            SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);
        }

        $message = 'Notification settings updated successfully.';
        if ($removedNonMeet > 0) {
            $message .= " Removed {$removedNonMeet} event(s) without a Google Meet link.";
        }

        return redirect()->route('integration.google-calendar', ['tab' => 'notifications'])
            ->with('success', $message);
    }

    public function oauthRedirect(): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        if (! GoogleCalendarApiService::isOAuthConfigured()) {
            return redirect()->route('integration.google-calendar', ['tab' => 'connect'])
                ->with('error', 'Google OAuth is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your .env.');
        }

        return $this->api->getOAuthProvider()
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function oauthCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = $this->api->getOAuthProvider()->user();
        } catch (\Throwable) {
            return redirect()->route('integration.google-calendar', ['tab' => 'connect'])
                ->with('error', 'Google authorization failed. Please try again.');
        }

        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->firstOrCreate(
            ['user_id' => $userId],
            ['status' => \App\Domains\ThirdParty\Enums\IntegrationStatus::Disabled, 'settings' => []]
        );

        $settings                     = $integration->settings ?? [];
        $settings['access_token']     = $googleUser->token;
        $settings['refresh_token']    = $googleUser->refreshToken ?: ($settings['refresh_token'] ?? null);
        $settings['token_expires_at'] = now()->addHour()->toDateTimeString();
        $settings['user_email']       = $googleUser->getEmail();
        $settings['google_account_name'] = $googleUser->getName();
        $settings['calendar_id']      = $settings['calendar_id'] ?? 'primary';
        unset($settings['sync_token']);

        $integration->settings = $settings;
        $integration->status   = \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled;
        $integration->save();

        $this->api->registerWatch($integration);
        $tenantId = tenancy()->tenant?->getTenantKey();
        SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);

        return redirect()->route('integration.google-calendar', ['tab' => 'notifications'])
            ->with('success', 'Google Calendar connected successfully.');
    }

    public function disconnect(): RedirectResponse
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();

        if ($integration) {
            $this->api->stopWatch($integration);
            $integration->settings        = [];
            $integration->first_synced_at = null;
            $integration->save();
        }

        return redirect()->route('integration.google-calendar', ['tab' => 'connect'])
            ->with('success', 'Google account disconnected.');
    }

    public function test(): JsonResponse
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();

        if (! $integration) {
            return response()->json(['success' => false, 'message' => 'Integration not found.'], 422);
        }

        $ok = $this->api->testConnection($integration);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Connected to Google Calendar.' : 'Connection failed. Reconnect your Google account.',
        ], $ok ? 200 : 422);
    }

    public function syncEvents(): JsonResponse
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();

        if (! $integration || ! $integration->hasRefreshToken()) {
            return response()->json(['success' => false, 'message' => 'Connect Google Calendar first.']);
        }

        $tenantId = tenancy()->tenant?->getTenantKey();
        SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Sync started in background. Events will update shortly.',
        ]);
    }

    public function createMeeting(CreateMeetingRequest $request): RedirectResponse
    {
        $validated   = $request->validated();
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();

        if (! $integration || ! $integration->hasRefreshToken()) {
            return redirect()->route('integration.google-calendar', ['tab' => 'create'])
                ->with('error', 'Connect Google Calendar first.');
        }

        $timezone = config('app.timezone');
        $start    = Carbon::parse($validated['start_at'], $timezone);
        $end      = $start->copy()->addMinutes((int) $validated['duration_minutes']);

        $googleEvent = $this->api->createEventWithMeet(
            $integration,
            $validated['summary'],
            $start,
            $end,
            $validated['attendee_email'] ?? null,
            $validated['attendee_name'],
            $validated['attendee_phone'],
            $validated['description'] ?? null,
        );

        if (! $googleEvent) {
            $message = $this->api->hasOverlappingEvent($integration, $start, $end)
                ? 'This time slot already has a meeting. Pick another time.'
                : 'Could not create Google Calendar event.';

            return redirect()->route('integration.google-calendar', ['tab' => 'create'])
                ->withInput()
                ->with('error', $message);
        }

        $tenantId = tenancy()->tenant?->getTenantKey();
        SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);

        return redirect()->route('integration.google-calendar', ['tab' => 'events'])
            ->with('success', 'Meeting created with Google Meet link.');
    }

    public function updateBookingAvailability(UpdateBookingAvailabilityRequest $request): RedirectResponse
    {
        $userId      = (int) auth()->id();
        $integration = GoogleCalendarIntegration::query()->firstOrCreate(['user_id' => $userId]);
        $normalized  = $this->availability->normalizeAvailability(
            $request->input('booking_availability', [])
        );

        $error = $this->availability->validate($normalized);
        if ($error !== null) {
            return redirect()->route('integration.google-calendar', ['tab' => 'booking'])
                ->with('error', $error);
        }

        $settings                       = $integration->settings ?? [];
        $settings['booking_availability'] = $normalized;
        $integration->settings          = $settings;
        $integration->save();

        return redirect()->route('integration.google-calendar', ['tab' => 'booking'])
            ->with('success', 'Booking availability updated.');
    }

    public function storeBookingLink(StoreBookingLinkRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $slug      = Str::slug($validated['title']) . '-' . Str::random(6);

        GoogleCalendarBookingLink::query()->create([
            'user_id'          => auth()->id(),
            'slug'             => $slug,
            'title'            => $validated['title'],
            'duration_minutes' => (int) $validated['duration_minutes'],
            'status'           => \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled,
            'settings'         => [],
        ]);

        return redirect()->route('integration.google-calendar', ['tab' => 'booking'])
            ->with('success', 'Booking link created.');
    }

    public function deleteBookingLink(int $id): RedirectResponse
    {
        GoogleCalendarBookingLink::query()
            ->where('user_id', auth()->id())
            ->where('id', $id)
            ->forceDelete();

        return redirect()->route('integration.google-calendar', ['tab' => 'booking'])
            ->with('success', 'Booking link removed.');
    }

    public function receiveWebhook(Request $request): \Illuminate\Http\Response
    {
        $channelId     = $request->header('X-Goog-Channel-ID');
        $resourceState = $request->header('X-Goog-Resource-State');

        $integration = null;
        if ($channelId) {
            $integration = GoogleCalendarIntegration::query()
                ->where('status', \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled)
                ->get()
                ->first(fn ($row) => ($row->settings['watch_channel_id'] ?? null) === $channelId);
        }

        GoogleCalendarWebhookLog::query()->create([
            'user_id'   => $integration?->user_id,
            'headers'   => $request->headers->all(),
            'payload'   => ['resource_state' => $resourceState, 'channel_id' => $channelId],
            'processed' => false,
        ]);

        if ($integration && in_array($resourceState, ['exists', 'sync'], true)) {
            $tenantId = tenancy()->tenant?->getTenantKey();
            SyncGoogleCalendarEventsJob::dispatch($integration->id, (string) $tenantId);
        }

        return response('', 200);
    }
}
