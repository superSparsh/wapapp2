<?php

declare(strict_types=1);

namespace Tests\Unit\ThirdParty;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GoogleCalendarListEventsTest extends TestCase
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

    public function test_list_events_with_sync_token_omits_order_by_and_includes_conference_data_version(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'token_expires_at' => now()->addHour()->toIso8601String(),
                'calendar_id' => 'primary',
                'sync_token' => 'SYNC-1',
            ],
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at', 'expires_in' => 3600], 200),
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response([
                'items' => [[
                    'id' => 'ev-1',
                    'status' => 'confirmed',
                    'summary' => 'Meet',
                    'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                    'start' => ['dateTime' => now()->addDay()->toIso8601String()],
                    'end' => ['dateTime' => now()->addDay()->addHour()->toIso8601String()],
                ]],
                'nextSyncToken' => 'SYNC-2',
            ], 200),
        ]);

        $result = (new GoogleCalendarApiService())->listEvents($integration, 'SYNC-1');

        $this->assertCount(1, $result['items']);
        $this->assertSame('SYNC-2', $result['nextSyncToken']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/calendars/')) {
                return false;
            }

            $data = $request->data();

            return ($data['syncToken'] ?? null) === 'SYNC-1'
                && ($data['conferenceDataVersion'] ?? null) == 1
                && ! array_key_exists('orderBy', $data)
                && ! array_key_exists('timeMin', $data)
                && ! array_key_exists('timeMax', $data);
        });
    }

    public function test_list_events_resets_sync_token_on_400_and_retries_full_fetch(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'token_expires_at' => now()->addHour()->toIso8601String(),
                'calendar_id' => 'primary',
                'sync_token' => 'BAD-TOKEN',
            ],
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'at', 'expires_in' => 3600], 200);
            }

            if (($request['syncToken'] ?? null) === 'BAD-TOKEN') {
                return Http::response(['error' => ['message' => 'Sync token cannot be used with orderBy']], 400);
            }

            return Http::response([
                'items' => [[
                    'id' => 'ev-full',
                    'status' => 'confirmed',
                    'summary' => 'Full Fetch Meet',
                    'hangoutLink' => 'https://meet.google.com/full-fetch-mtg',
                    'start' => ['dateTime' => now()->addDay()->toIso8601String()],
                    'end' => ['dateTime' => now()->addDay()->addHour()->toIso8601String()],
                ]],
                'nextSyncToken' => 'GOOD-TOKEN',
            ], 200);
        });

        $result = (new GoogleCalendarApiService())->listEvents($integration, 'BAD-TOKEN');

        $this->assertCount(1, $result['items']);
        $this->assertSame('GOOD-TOKEN', $result['nextSyncToken']);
        $this->assertArrayNotHasKey('sync_token', $integration->fresh()->settings);
    }
}
