<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\OptInTemplateService;
use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OptInTemplateServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        config([
            'whatsapp.alibaba.access_key_id' => 'test_key',
            'whatsapp.alibaba.access_key_secret' => 'test_secret',
            'whatsapp.alibaba.endpoint' => 'cams.ap-southeast-1.aliyuncs.com',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_ensure_template_creates_with_legacy_variable_syntax_and_samples(): void
    {
        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::sequence()
                ->push(['Code' => 'OK', 'List' => []], 200)
                ->push(['Code' => 'OK', 'Data' => ['TemplateCode' => '1257583503568572416']], 200),
        ]);

        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
            'is_default' => true,
        ]);

        $template = app(OptInTemplateService::class)->ensureTemplate($line);

        $this->assertSame(OptInTemplateService::TEMPLATE_NAME, $template->name);
        $this->assertStringContainsString(
            TemplateVariableSyntax::placeholder('full_name'),
            (string) data_get($template->payload, 'body.text'),
        );
        $this->assertStringNotContainsString('{{full_name}}', (string) data_get($template->payload, 'body.text'));
        $this->assertSame(['John Doe'], data_get($template->payload, 'body.samples'));
        $this->assertNotSame(OptInTemplateService::TEMPLATE_NAME, $template->code);
    }

    public function test_ensure_template_repairs_rejected_and_auto_submits(): void
    {
        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::sequence()
                ->push(['Code' => 'OK', 'List' => []], 200)
                ->push(['Code' => 'OK', 'Data' => ['TemplateCode' => '1257583503568572999']], 200),
        ]);

        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
            'is_default' => true,
        ]);

        $template = Template::factory()->create([
            'name' => OptInTemplateService::TEMPLATE_NAME,
            'code' => OptInTemplateService::TEMPLATE_NAME,
            'whatsapp_line_id' => $line->id,
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::Rejected,
            'rejection_reason' => 'INVALID_FORMAT(Duplicate content or missing examples.)',
            'synced_at' => null,
            'payload' => [
                'meta' => [
                    'name' => OptInTemplateService::TEMPLATE_NAME,
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'setup_completed' => true,
                ],
                'body' => [
                    'text' => "Hi {{full_name}}!\nWe want to make sure you never miss out.",
                    'samples' => [],
                ],
                'footer' => ['text' => ''],
                'button_mode' => 'quick_reply',
                'buttons' => [
                    ['text' => 'Yes', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ],
                'is_opt_out' => false,
            ],
            'body_preview' => 'Hi {{full_name}}!',
        ]);

        $result = app(OptInTemplateService::class)->ensureTemplate($line);
        $result->refresh();

        $this->assertSame($template->id, $result->id);
        $this->assertStringContainsString(
            TemplateVariableSyntax::placeholder('full_name'),
            (string) data_get($result->payload, 'body.text'),
        );
        $this->assertSame(['John Doe'], data_get($result->payload, 'body.samples'));
        $this->assertNotSame(OptInTemplateService::TEMPLATE_NAME, $result->code);
        $this->assertNotEmpty(data_get($result->payload, 'meta.opt_in_auto_submit_at'));
        // Duplicate reject → alternate wording variant.
        $this->assertSame(1, (int) data_get($result->payload, 'meta.opt_in_content_variant'));
    }

    public function test_ensure_template_throttles_repeat_auto_submit(): void
    {
        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
            'is_default' => true,
        ]);

        $nameVar = TemplateVariableSyntax::placeholder('full_name');
        $body = 'Hi '.$nameVar."!\n"
            .'We want to make sure you never miss out on our latest updates and exclusive benefits. '
            .'By opting in, you will get instant access to special offers, seasonal promotions, '
            ."and important account alerts directly here on WhatsApp.\n"
            .'Would you like to stay connected with us?';

        $payload = Template::defaultPayload();
        $payload['meta'] = [
            'name' => OptInTemplateService::TEMPLATE_NAME,
            'category' => 'MARKETING',
            'language' => 'en_GB',
            'template_type' => 'regular',
            'setup_completed' => true,
            'opt_in_auto_submit_at' => now()->toIso8601String(),
            'opt_in_content_variant' => 0,
        ];
        $payload['body'] = [
            'text' => $body,
            'samples' => ['John Doe'],
        ];
        $payload['button_mode'] = 'quick_reply';
        $payload['is_opt_out'] = false;
        $payload['buttons'] = [
            ['text' => 'Yes', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ['text' => 'No', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ['text' => 'STOP', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
        ];

        Template::factory()->create([
            'name' => OptInTemplateService::TEMPLATE_NAME,
            'code' => null,
            'whatsapp_line_id' => $line->id,
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::PendingReview,
            'synced_at' => null,
            'payload' => $payload,
            'body_preview' => $body,
        ]);

        Http::fake();

        app(OptInTemplateService::class)->ensureTemplate($line);

        Http::assertNothingSent();
    }
}
