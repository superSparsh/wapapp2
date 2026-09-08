<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google Calendar API service — optimized port from legacy GoogleCalendarApiService.
 *
 * Key optimizations:
 * - Token auto-refresh: checks expiry in memory before hitting API
 * - Refresh token persisted to DB in one save
 * - createEventWithMeet: two-step only if needed (avoids duplicate Meet links)
 * - listEvents: incremental sync via syncToken, 410 fallback
 */
class GoogleCalendarApiService
{
    public const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar';
    public const EVENTS_SCOPE   = 'https://www.googleapis.com/auth/calendar.events';

    // ──────────────────────────────────────────────────────────────────────────
    // OAuth helpers
    // ──────────────────────────────────────────────────────────────────────────

    public static function isOAuthConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function oauthRedirectUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/integration/google-calendar/oauth/callback';
    }

    /**
     * @return \Laravel\Socialite\Contracts\Provider
     */
    public function getOAuthProvider(): mixed
    {
        return \Laravel\Socialite\Facades\Socialite::buildProvider(
            \Laravel\Socialite\Two\GoogleProvider::class,
            [
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect'      => $this->oauthRedirectUrl(),
            ]
        )->scopes([
            self::CALENDAR_SCOPE,
            self::EVENTS_SCOPE,
            'openid',
            'email',
            'profile',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Token management
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return a valid access token, refreshing via the refresh_token if expired.
     * Persists the new token to the integration record.
     */
    public function getValidAccessToken(GoogleCalendarIntegration $integration): ?string
    {
        $settings   = $integration->settings ?? [];
        $accessToken = $settings['access_token'] ?? null;
        $expiresAt   = $settings['token_expires_at'] ?? null;

        if ($accessToken && $expiresAt && Carbon::parse($expiresAt)->isFuture()) {
            return $accessToken;
        }

        $refreshToken = $settings['refresh_token'] ?? null;
        if (! $refreshToken) {
            return $accessToken;
        }

        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $settings['access_token']    = $data['access_token'];
        $settings['token_expires_at'] = now()->addSeconds((int) ($data['expires_in'] ?? 3600))->toDateTimeString();
        $integration->settings = $settings;
        $integration->save();

        return $settings['access_token'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Calendar operations
    // ──────────────────────────────────────────────────────────────────────────

    public function testConnection(GoogleCalendarIntegration $integration): bool
    {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return false;
        }

        $response = Http::withToken($token)->timeout(10)->get(
            'https://www.googleapis.com/calendar/v3/users/me/calendarList',
            ['maxResults' => 1]
        );

        return $response->successful();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCalendars(GoogleCalendarIntegration $integration): array
    {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return [];
        }

        $response = Http::withToken($token)->timeout(15)->get(
            'https://www.googleapis.com/calendar/v3/users/me/calendarList',
            ['minAccessRole' => 'writer']
        );

        return $response->successful() ? ($response->json('items') ?? []) : [];
    }

    /**
     * List events, using incremental syncToken when available.
     * Falls back to full fetch on HTTP 410 (invalid sync token).
     *
     * @return array{items: array<int, array<string, mixed>>, nextSyncToken: string|null}
     */
    public function listEvents(GoogleCalendarIntegration $integration, ?string $syncToken = null): array
    {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return ['items' => [], 'nextSyncToken' => null];
        }

        $calendarId = urlencode($integration->calendarId());
        $params     = ['singleEvents' => 'true', 'orderBy' => 'startTime', 'maxResults' => 250];

        if ($syncToken) {
            $params['syncToken'] = $syncToken;
        } else {
            $params['timeMin'] = Carbon::now('UTC')->subMonths(1)->toRfc3339String();
            $params['timeMax'] = Carbon::now('UTC')->addMonths(3)->toRfc3339String();
        }

        $meetOnly      = $integration->isMeetOnly();
        $allItems      = [];
        $pageToken     = null;
        $nextSyncToken = null;

        do {
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::withToken($token)->timeout(30)->get(
                "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events",
                $params
            );

            // Sync token expired — reset and retry with full fetch
            if ($response->status() === 410 && $syncToken) {
                $settings = $integration->settings;
                unset($settings['sync_token']);
                $integration->settings = $settings;
                $integration->save();

                return $this->listEvents($integration, null);
            }

            if (! $response->successful()) {
                break;
            }

            foreach (($response->json('items') ?? []) as $item) {
                if ($meetOnly && ! $this->extractMeetLink($item)) {
                    continue;
                }
                $allItems[] = $item;
            }

            $pageToken     = $response->json('nextPageToken');
            $nextSyncToken = $response->json('nextSyncToken') ?? $nextSyncToken;
        } while ($pageToken);

        return ['items' => $allItems, 'nextSyncToken' => $nextSyncToken];
    }

    /**
     * Create a Google Calendar event with an auto-attached Google Meet link.
     * Step 1: create event. Step 2: patch conferenceData only if no Meet link was auto-attached.
     *
     * @return array<string, mixed>|null
     */
    public function createEventWithMeet(
        GoogleCalendarIntegration $integration,
        string $summary,
        Carbon $start,
        Carbon $end,
        ?string $attendeeEmail = null,
        ?string $attendeeName  = null,
        ?string $phone         = null,
        ?string $description   = null,
    ): ?array {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return null;
        }

        if ($this->hasOverlappingEvent($integration, $start, $end)) {
            return null;
        }

        $calendarId        = $integration->calendarId();
        $calendarIdEncoded = urlencode($calendarId);
        $timezone          = config('app.timezone', 'UTC');

        $body = [
            'summary'     => $summary,
            'description' => $this->buildDescription($description, $phone, $attendeeName),
            'start'       => ['dateTime' => $start->copy()->timezone($timezone)->toRfc3339String(), 'timeZone' => $timezone],
            'end'         => ['dateTime' => $end->copy()->timezone($timezone)->toRfc3339String(), 'timeZone' => $timezone],
        ];

        if ($attendeeEmail) {
            $body['attendees'] = [['email' => $attendeeEmail, 'displayName' => $attendeeName ?? $attendeeEmail]];
        }

        $response = Http::withToken($token)->timeout(30)->post(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendarIdEncoded}/events",
            $body
        );

        if (! $response->successful()) {
            return null;
        }

        $event = $response->json();

        // If Google auto-attached a Meet link, we're done
        if ($this->extractMeetLink($event)) {
            return $event;
        }

        // Patch to request a Meet link via conferenceData
        $eventId = urlencode((string) ($event['id'] ?? ''));
        if ($eventId === '') {
            return $event;
        }

        $patchResponse = Http::withToken($token)->timeout(30)->patch(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendarIdEncoded}/events/{$eventId}?conferenceDataVersion=1",
            [
                'conferenceData' => [
                    'createRequest' => [
                        'requestId'            => Str::uuid()->toString(),
                        'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                    ],
                ],
            ]
        );

        return $patchResponse->successful() ? $patchResponse->json() : $event;
    }

    /**
     * Get busy periods from Google's freeBusy API.
     *
     * @return array<int, array{start: string, end: string}>|null
     */
    public function getFreeBusyPeriods(
        GoogleCalendarIntegration $integration,
        Carbon $timeMinUtc,
        Carbon $timeMaxUtc,
    ): ?array {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return null;
        }

        $calendarId = $integration->calendarId();
        $response   = Http::withToken($token)->timeout(15)->post('https://www.googleapis.com/calendar/v3/freeBusy', [
            'timeMin' => $timeMinUtc->toRfc3339String(),
            'timeMax' => $timeMaxUtc->toRfc3339String(),
            'items'   => [['id' => $calendarId]],
        ]);

        if (! $response->successful()) {
            return null;
        }

        $calendars = $response->json('calendars') ?? [];
        $busy      = $calendars[$calendarId]['busy'] ?? [];
        $periods   = [];

        foreach ($busy as $block) {
            if (! empty($block['start']) && ! empty($block['end'])) {
                $periods[] = ['start' => $block['start'], 'end' => $block['end']];
            }
        }

        return $periods;
    }

    /**
     * Check whether a time slot already has an event (prevents double-booking).
     */
    public function hasOverlappingEvent(GoogleCalendarIntegration $integration, Carbon $start, Carbon $end): bool
    {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return false;
        }

        $calendarId = urlencode($integration->calendarId());
        $response   = Http::withToken($token)->timeout(15)->get(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events",
            [
                'timeMin'      => $start->copy()->utc()->toRfc3339String(),
                'timeMax'      => $end->copy()->utc()->toRfc3339String(),
                'singleEvents' => 'true',
            ]
        );

        if (! $response->successful()) {
            return false;
        }

        foreach (($response->json('items') ?? []) as $item) {
            if (($item['status'] ?? '') !== 'cancelled') {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Watch / push notifications
    // ──────────────────────────────────────────────────────────────────────────

    public function registerWatch(GoogleCalendarIntegration $integration): bool
    {
        $token = $this->getValidAccessToken($integration);
        if (! $token) {
            return false;
        }

        $this->stopWatch($integration);

        $calendarId = urlencode($integration->calendarId());
        $channelId  = (string) Str::uuid();
        $expiration = now()->addDays(6)->timestamp * 1000;

        $response = Http::withToken($token)->timeout(15)->post(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/watch",
            [
                'id'         => $channelId,
                'type'       => 'web_hook',
                'address'    => route('google-calendar.webhook'),
                'expiration' => $expiration,
            ]
        );

        if (! $response->successful()) {
            return false;
        }

        $resource = $response->json();
        $settings = $integration->settings;
        $settings['watch_channel_id']  = $channelId;
        $settings['watch_resource_id'] = $resource['resourceId'] ?? null;
        $settings['watch_expiration']  = $resource['expiration'] ?? $expiration;
        $integration->settings = $settings;
        $integration->save();

        return true;
    }

    public function stopWatch(GoogleCalendarIntegration $integration): void
    {
        $settings   = $integration->settings ?? [];
        $channelId  = $settings['watch_channel_id'] ?? null;
        $resourceId = $settings['watch_resource_id'] ?? null;
        $token      = $this->getValidAccessToken($integration);

        if ($token && $channelId && $resourceId) {
            Http::withToken($token)->timeout(10)->post('https://www.googleapis.com/calendar/v3/channels/stop', [
                'id'         => $channelId,
                'resourceId' => $resourceId,
            ]);
        }

        unset($settings['watch_channel_id'], $settings['watch_resource_id'], $settings['watch_expiration']);
        $integration->settings = $settings;
        $integration->save();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Data extraction helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $event
     */
    public function extractMeetLink(array $event): ?string
    {
        if (! empty($event['hangoutLink'])) {
            return $event['hangoutLink'];
        }

        foreach (($event['conferenceData']['entryPoints'] ?? []) as $entry) {
            if (($entry['entryPointType'] ?? '') === 'video' && ! empty($entry['uri'])) {
                return $entry['uri'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $event
     */
    public function mapGoogleStatus(array $event): string
    {
        return ($event['status'] ?? '') === 'cancelled' ? 'canceled' : 'active';
    }

    /**
     * @param array<string, mixed> $event
     * @return array{start: Carbon|null, end: Carbon|null}
     */
    public function parseEventTimes(array $event): array
    {
        $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
        $end   = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;

        return [
            'start' => $start ? Carbon::parse($start) : null,
            'end'   => $end   ? Carbon::parse($end)   : null,
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{email: string|null, name: string|null, payload: array<string, mixed>|null}
     */
    public function extractPrimaryAttendee(array $event, int $organizerUserId): array
    {
        $organizerEmail = strtolower($event['organizer']['email'] ?? '');

        foreach (($event['attendees'] ?? []) as $attendee) {
            $email = strtolower($attendee['email'] ?? '');
            if ($email && $email !== $organizerEmail && empty($attendee['self'])) {
                return [
                    'email'   => $attendee['email'],
                    'name'    => $attendee['displayName'] ?? $attendee['email'],
                    'payload' => $attendee,
                ];
            }
        }

        return ['email' => null, 'name' => null, 'payload' => null];
    }

    public function extractPhoneFromDescription(?string $description): ?string
    {
        if (! $description) {
            return null;
        }

        if (preg_match('/phone\s*[:=]\s*([+\d\s\-()]{10,20})/i', $description, $m)) {
            $phone = preg_replace('/\s+/', '', $m[1]);
            if (strlen(preg_replace('/\D/', '', $phone) ?: '') >= 10) {
                return $phone;
            }
        }

        return null;
    }

    public function extractGuestNameFromDescription(?string $description): ?string
    {
        if (! $description) {
            return null;
        }

        if (preg_match('/guest\s*[:=]\s*([^\n\r]+)/i', $description, $m)) {
            $name = trim((string) ($m[1] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    protected function buildDescription(?string $description, ?string $phone, ?string $name): string
    {
        $parts = array_filter([
            $description,
            $name  ? "Guest: {$name}"   : null,
            $phone ? "Phone: {$phone}" : null,
        ]);

        return implode("\n", $parts);
    }
}
