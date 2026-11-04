<?php

declare(strict_types=1);

namespace Tests\Unit\ThirdParty;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GoogleCalendarCreateEventWithMeetTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_create_event_with_meet_sends_conference_data_version_on_insert(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'token_expires_at' => now()->addHour()->toDateTimeString(),
                'calendar_id' => 'primary',
            ],
        ]);

        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*/events*' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response(['items' => []], 200);
                }

                return Http::response([
                    'id' => 'evt-meet-1',
                    'summary' => 'Intro Call',
                    'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                    'htmlLink' => 'https://calendar.google.com/event?eid=1',
                    'start' => ['dateTime' => now()->addDay()->toIso8601String()],
                    'end' => ['dateTime' => now()->addDay()->addHour()->toIso8601String()],
                ], 200);
            },
        ]);

        $start = Carbon::now('UTC')->addDay()->startOfHour();
        $end = $start->copy()->addMinutes(30);

        $event = (new GoogleCalendarApiService())->createEventWithMeet(
            $integration,
            'Intro Call',
            $start,
            $end,
            'guest@example.com',
            'Guest',
            '+919876543210',
            null,
        );

        $this->assertIsArray($event);
        $this->assertSame('evt-meet-1', $event['id']);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $event['hangoutLink']);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/events')) {
                return false;
            }

            $urlHasVersion = str_contains($request->url(), 'conferenceDataVersion=1');
            $body = $request->data();
            $hasCreateRequest = ($body['conferenceData']['createRequest']['conferenceSolutionKey']['type'] ?? null) === 'hangoutsMeet';
            $sendsUpdates = str_contains($request->url(), 'sendUpdates=all');

            return $urlHasVersion && $hasCreateRequest && $sendsUpdates;
        });
    }

    public function test_create_event_with_meet_surfaces_google_error_message(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'token_expires_at' => now()->addHour()->toDateTimeString(),
                'calendar_id' => 'primary',
            ],
        ]);

        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*/events*' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response(['items' => []], 200);
                }

                return Http::response([
                    'error' => ['message' => 'Invalid conference type value.'],
                ], 400);
            },
        ]);

        $api = new GoogleCalendarApiService();
        $result = $api->createEventWithMeet(
            $integration,
            'Intro Call',
            Carbon::now('UTC')->addDay(),
            Carbon::now('UTC')->addDay()->addHour(),
            null,
            'Guest',
            '+919876543210',
        );

        $this->assertNull($result);
        $this->assertStringContainsString('Invalid conference type value.', (string) $api->lastError());
    }
}
