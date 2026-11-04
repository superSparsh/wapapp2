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
            'operational-alerts.whatsapp.cust_space_id' => 'space-1',
            'operational-alerts.whatsapp.language' => 'en_GB',
        ]);
    }

    public function test_send_template_result_treats_cams_business_error_as_failure(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $action = $request['Action'] ?? '';
            if ($action === 'ListChatappTemplate') {
                return Http::response([
                    'Code' => 'OK',
                    'Data' => [
                        'ListTemplate' => [
                            ['TemplateCode' => '1257583503568572001', 'TemplateName' => 'calendly_customer_invite_created'],
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'Code' => 'TemplateNotExist',
                'Message' => 'The template does not exist.',
                'RequestId' => 'req-1',
            ], 200);
        });

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
            '1257583503568572001',
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
            '1257583503568572001',
            ['name' => 'Guest'],
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $action = $request['Action'] ?? '';
            if ($action !== 'SendChatappMessage') {
                return false;
            }

            $to = $request['To'] ?? null;

            return is_string($to)
                && $to === '919876543210'
                && ! str_contains($to, '+');
        });
    }

    public function test_send_template_resolves_snake_case_name_to_provider_code(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $action = $request['Action'] ?? '';
            if ($action === 'ListChatappTemplate') {
                return Http::response([
                    'Code' => 'OK',
                    'Data' => [
                        'ListTemplate' => [
                            [
                                'TemplateCode' => '1257583503568572999',
                                'TemplateName' => 'calendly_customer_invite_created',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['Code' => 'OK', 'Message' => 'success'], 200);
        });

        $result = app(OperationalWhatsAppSender::class)->sendTemplateResult(
            '919876543210',
            'calendly_customer_invite_created',
            ['name' => 'Guest'],
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return ($request['Action'] ?? '') === 'SendChatappMessage'
                && ($request['TemplateCode'] ?? null) === '1257583503568572999';
        });
    }

    public function test_send_template_fails_clearly_when_name_not_on_space(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (($request['Action'] ?? '') === 'ListChatappTemplate') {
                return Http::response([
                    'Code' => 'OK',
                    'Data' => ['ListTemplate' => []],
                ], 200);
            }

            return Http::response(['Code' => 'OK'], 200);
        });

        $result = app(OperationalWhatsAppSender::class)->sendTemplateResult(
            '919876543210',
            'calendly_customer_invite_created',
            ['name' => 'Guest'],
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('calendly_customer_invite_created', (string) $result['error']);
        $this->assertStringContainsString('not found', (string) $result['error']);
    }

    public function test_send_template_matches_prefixed_meta_name_and_retries_language_on_not_exist(): void
    {
        $sendAttempts = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$sendAttempts) {
            $action = $request['Action'] ?? '';
            if ($action === 'ListChatappTemplate') {
                // Short Name miss; unfiltered list returns prefixed Meta real name.
                if (($request['Name'] ?? null) === 'google_calendar_customer_event_created') {
                    return Http::response(['Code' => 'OK', 'Data' => ['ListTemplate' => []]], 200);
                }

                return Http::response([
                    'Code' => 'OK',
                    'Data' => [
                        'ListTemplate' => [
                            [
                                'TemplateCode' => '1257583503568572333',
                                'TemplateName' => '42_google_calendar_customer_event_created',
                                'Language' => 'en',
                            ],
                        ],
                    ],
                ], 200);
            }

            $sendAttempts++;
            if (($request['Language'] ?? null) === 'en_GB') {
                return Http::response([
                    'Code' => 'TemplateNotExist',
                    'Message' => 'The template is not exist.',
                ], 200);
            }

            return Http::response([
                'Code' => 'OK',
                'Message' => 'success',
                'MessageId' => 'msg-lang-ok',
            ], 200);
        });

        $result = app(OperationalWhatsAppSender::class)->sendTemplateResult(
            '919876543210',
            'google_calendar_customer_event_created',
            ['full_name' => 'Guest'],
            ['language' => 'en_GB'],
        );

        $this->assertTrue($result['ok'], (string) ($result['error'] ?? ''));
        $this->assertGreaterThanOrEqual(1, $sendAttempts);

        Http::assertSent(function ($request) {
            return ($request['Action'] ?? '') === 'SendChatappMessage'
                && ($request['TemplateCode'] ?? null) === '1257583503568572333'
                && ($request['Language'] ?? null) === 'en';
        });
    }
}
