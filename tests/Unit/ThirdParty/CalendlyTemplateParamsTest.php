<?php

declare(strict_types=1);

namespace Tests\Unit\ThirdParty;

use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Services\CalendlyEventSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CalendlyTemplateParamsTest extends TestCase
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
        $event = CalendlyEvent::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 'ev-params-1',
            'status' => 'active',
            'event_type' => 'consultation',
            'invitee_email' => 'guest@example.com',
            'whatsapp_number' => '919876543210',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'raw_payload' => [
                'name' => '30 Minute Meeting',
            ],
            'form_responses' => [
                'name' => 'Ada Lovelace',
                'reschedule_url' => 'https://calendly.com/reschedulings/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'cancel_url' => 'https://calendly.com/cancellations/11111111-2222-3333-4444-555555555555',
            ],
        ]);

        $params = app(CalendlyEventSyncService::class)->templateParamsForEvent($event);

        $this->assertSame([
            'full_name',
            'calendly_event_name',
            'calendly_event_start_time',
            'calendly_event_end_time',
            'calendly_event_join_link',
            'reschedule_link',
            'cancel_link',
        ], array_keys($params));

        $this->assertSame('Ada Lovelace', $params['full_name']);
        $this->assertSame('30 Minute Meeting', $params['calendly_event_name']);
        $this->assertSame('N/A', $params['calendly_event_join_link']);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $params['reschedule_link']);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $params['cancel_link']);
        $this->assertArrayNotHasKey('whatsapp_number', $params);
        $this->assertArrayNotHasKey('name', $params);
        $this->assertArrayNotHasKey('meet', $params);
    }

    public function test_admin_params_include_guest_contact_fields(): void
    {
        $event = CalendlyEvent::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 'ev-params-2',
            'status' => 'active',
            'event_type' => 'consultation',
            'invitee_email' => 'guest@example.com',
            'whatsapp_number' => '+91 98765 43210',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'raw_payload' => [
                'name' => 'Demo',
                'location' => ['join_url' => 'https://meet.example.com/abc'],
            ],
            'form_responses' => ['name' => 'Guest'],
        ]);

        $params = app(CalendlyEventSyncService::class)->templateParamsForEvent($event, forAdmin: true);

        $this->assertSame('https://meet.example.com/abc', $params['calendly_event_join_link']);
        $this->assertSame('+91 98765 43210', $params['whatsapp_number']);
        $this->assertSame('guest@example.com', $params['invitee_email']);
        $this->assertSame('N/A', $params['reschedule_link']);
        $this->assertSame('N/A', $params['cancel_link']);
    }
}
