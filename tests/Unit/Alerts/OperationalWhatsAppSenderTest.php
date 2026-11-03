<?php

declare(strict_types=1);

namespace Tests\Unit\Alerts;

use App\Domains\Alerts\Services\OperationalWhatsAppSender;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalWhatsAppSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'operational-alerts.enabled' => true,
            'operational-alerts.alibaba.access_key_id' => 'test-key',
            'operational-alerts.alibaba.access_key_secret' => 'test-secret',
            'operational-alerts.whatsapp.from' => '919999999999',
            'operational-alerts.whatsapp.language' => 'en_GB',
        ]);
    }

    public function test_send_template_result_treats_cams_business_error_as_failure(): void
    {
        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'TemplateNotExist',
                'Message' => 'The template does not exist.',
                'RequestId' => 'req-1',
            ], 200),
        ]);

        $result = app(OperationalWhatsAppSender::class)->sendTemplateResult(
            '919876543210',
            'calendly_customer_invite_created',
            ['name' => 'Guest', 'event' => 'Demo'],
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('The template does not exist.', (string) $result['error']);
        $this->assertStringContainsString('TemplateNotExist', (string) $result['error']);
    }

    public function test_send_template_result_succeeds_on_code_ok(): void
    {
        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'Message' => 'success',
                'MessageId' => 'msg-1',
            ], 200),
        ]);

        $result = app(OperationalWhatsAppSender::class)->sendTemplateResult(
            '919876543210',
            'calendly_customer_invite_created',
            ['name' => 'Guest'],
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
    }

    public function test_send_template_sends_to_as_digits_only_without_plus(): void
    {
        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'Message' => 'success',
            ], 200),
        ]);

        $result = app(OperationalWhatsAppSender::class)->sendTemplateResult(
            '+91 98765-43210',
            'calendly_customer_invite_created',
            ['name' => 'Guest'],
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $to = $request['To'] ?? null;

            return is_string($to)
                && $to === '919876543210'
                && ! str_contains($to, '+');
        });
    }
}
