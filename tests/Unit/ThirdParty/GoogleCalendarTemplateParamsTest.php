<?php

declare(strict_types=1);

namespace Tests\Unit\ThirdParty;

use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Services\GoogleCalendarEventSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GoogleCalendarTemplateParamsTest extends TestCase
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

    public function test_customer_params_match_legacy_cams_keys_with_na_fallbacks(): void
    {
        $event = GoogleCalendarEvent::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 'gev-params-1',
            'calendar_id' => 'primary',
            'status' => 'active',
            'summary' => 'Product Demo',
            'invitee_name' => 'Ada Lovelace',
            'invitee_email' => 'guest@example.com',
            'whatsapp_number' => '919876543210',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'meet_link' => null,
            'calendar_link' => null,
        ]);

        $params = app(GoogleCalendarEventSyncService::class)->templateParamsForEvent($event);

        $this->assertSame([
            'full_name',
            'google_event_name',
            'google_event_start_time',
            'google_event_end_time',
            'google_meet_link',
            'google_meet_code',
            'meet_code',
            'google_calendar_link',
            'google_calendar_code',
        ], array_keys($params));

        $this->assertSame('Ada Lovelace', $params['full_name']);
        $this->assertSame('Product Demo', $params['google_event_name']);
        $this->assertSame('N/A', $params['google_meet_link']);
        $this->assertSame('N/A', $params['google_meet_code']);
        $this->assertSame('N/A', $params['google_calendar_link']);
        $this->assertSame('N/A', $params['google_calendar_code']);
        $this->assertArrayNotHasKey('whatsapp_number', $params);
        $this->assertArrayNotHasKey('invitee_email', $params);
        $this->assertArrayNotHasKey('meet', $params);
        $this->assertArrayNotHasKey('link', $params);
    }

    public function test_admin_params_include_guest_contact_fields(): void
    {
        $event = GoogleCalendarEvent::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 'gev-params-2',
            'calendar_id' => 'primary',
            'status' => 'active',
            'summary' => 'Demo',
            'invitee_name' => 'Guest',
            'invitee_email' => 'guest@example.com',
            'whatsapp_number' => '+91 98765 43210',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'meet_link' => 'https://meet.google.com/abc-defg-hij',
            'calendar_link' => 'https://calendar.google.com/event?eid=xyz',
        ]);

        $params = app(GoogleCalendarEventSyncService::class)->templateParamsForEvent($event, forAdmin: true);

        $this->assertSame('https://meet.google.com/abc-defg-hij', $params['google_meet_link']);
        $this->assertSame('abc-defg-hij', $params['google_meet_code']);
        $this->assertSame('https://calendar.google.com/event?eid=xyz', $params['google_calendar_link']);
        $this->assertSame('xyz', $params['google_calendar_code']);
        $this->assertSame('+91 98765 43210', $params['whatsapp_number']);
        $this->assertSame('guest@example.com', $params['invitee_email']);
        $this->assertSame('Guest', $params['full_name']);
    }

    public function test_extract_google_meet_code_from_url_and_bare_code(): void
    {
        $sync = app(GoogleCalendarEventSyncService::class);

        $this->assertSame('abc-defg-hij', $sync->extractGoogleMeetCode('https://meet.google.com/abc-defg-hij'));
        $this->assertSame('abc-defg-hij', $sync->extractGoogleMeetCode('https://meet.google.com/abc-defg-hij?hs=122'));
        $this->assertSame('abc-defg-hij', $sync->extractGoogleMeetCode('abc-defg-hij'));
        $this->assertSame('N/A', $sync->extractGoogleMeetCode(null));
        $this->assertSame('N/A', $sync->extractGoogleMeetCode(''));
    }
}
