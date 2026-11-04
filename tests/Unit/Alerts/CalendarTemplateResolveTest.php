<?php

declare(strict_types=1);

namespace Tests\Unit\Alerts;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CalendarTemplateResolveTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        config([
            'operational-alerts.enabled' => true,
            'operational-alerts.alibaba.access_key_id' => 'test-key',
            'operational-alerts.alibaba.access_key_secret' => 'test-secret',
            'operational-alerts.google_calendar.customer_created' => 'google_calendar_customer_event_created',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_resolve_calendar_template_matches_prefixed_cams_name_and_returns_numeric_code(): void
    {
        $line = WhatsappLine::query()->firstOrFail();
        $line->forceFill([
            'is_default' => true,
            'phone' => '919999999999',
            'alibaba_cust_space_id' => 'space-1',
        ])->save();

        Template::query()->create([
            'whatsapp_line_id' => $line->id,
            'name' => '42_google_calendar_customer_event_created',
            'code' => '1257583503568572111',
            'status' => TemplateStatus::Approved,
            'language' => 'en',
            'category' => 'UTILITY',
            'source' => 'cams',
            'payload' => [
                'legacy_real_template_name' => '42_google_calendar_customer_event_created',
                'legacy_template_code' => '1257583503568572111',
            ],
        ]);

        $dispatcher = app(AlertDispatcher::class);
        $method = new ReflectionMethod(AlertDispatcher::class, 'resolveCalendarTemplate');
        $method->setAccessible(true);

        $resolved = $method->invoke($dispatcher, 'google_calendar_customer_event_created', $line);

        $this->assertSame('1257583503568572111', $resolved['code']);
        $this->assertSame('en', $resolved['language']);
        $this->assertNull($resolved['error']);
    }

    public function test_calendar_whatsapp_sends_numeric_code_with_template_language(): void
    {
        $line = WhatsappLine::query()->firstOrFail();
        $line->forceFill([
            'is_default' => true,
            'phone' => '919999999999',
            'alibaba_cust_space_id' => 'space-1',
        ])->save();

        Template::query()->create([
            'whatsapp_line_id' => $line->id,
            'name' => '42_google_calendar_customer_event_created',
            'code' => '1257583503568572222',
            'status' => TemplateStatus::Approved,
            'language' => 'en_US',
            'category' => 'UTILITY',
            'source' => 'cams',
            'payload' => [],
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'Message' => 'success',
                'MessageId' => 'msg-created-1',
            ], 200),
        ]);

        $result = app(AlertDispatcher::class)->calendarWhatsApp(
            'operational-alerts.google_calendar.customer_created',
            '919876543210',
            [
                'full_name' => 'Guest',
                'google_event_name' => 'Demo',
                'google_event_start_time' => '04 Nov 2026, 11:00 AM',
                'google_event_end_time' => '04 Nov 2026, 12:00 PM',
                'google_meet_link' => 'N/A',
                'google_meet_code' => 'N/A',
                'google_calendar_link' => 'N/A',
                'google_calendar_eid' => 'N/A',
            ],
        );

        $this->assertTrue($result['ok'], (string) ($result['error'] ?? ''));

        Http::assertSent(function ($request) {
            return ($request['Action'] ?? '') === 'SendChatappMessage'
                && ($request['TemplateCode'] ?? null) === '1257583503568572222'
                && ($request['Language'] ?? null) === 'en_US';
        });
    }
}
