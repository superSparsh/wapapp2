<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplateWhatsAppService;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateSubmitLineResolutionTest extends TestCase
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

    public function test_submit_marks_rejected_when_no_cust_space_id(): void
    {
        WhatsappLine::query()->update(['alibaba_cust_space_id' => null]);

        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'welcome_offer',
            'code' => null,
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::PendingReview,
            'synced_at' => null,
            'payload' => [
                'meta' => ['name' => 'welcome_offer', 'category' => 'MARKETING', 'language' => 'en_GB', 'setup_completed' => true],
                'body' => ['text' => 'Hello there', 'samples' => []],
                'footer' => ['text' => ''],
                'buttons' => [],
                'button_mode' => 'none',
            ],
            'body_preview' => 'Hello there',
        ]);

        Http::fake();

        $ok = app(TemplateWhatsAppService::class)->submitTemplate($template);

        $this->assertFalse($ok);
        $template->refresh();
        $this->assertSame(TemplateStatus::Rejected, $template->status);
        $this->assertStringContainsString('CustSpaceId', (string) $template->rejection_reason);
        Http::assertNothingSent();
    }

    public function test_submit_attaches_fallback_line_with_cust_space(): void
    {
        WhatsappLine::query()->update(['alibaba_cust_space_id' => null, 'is_default' => false]);

        $connected = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
            'is_default' => true,
        ]);

        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'welcome_offer',
            'code' => null,
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::PendingReview,
            'synced_at' => null,
            'payload' => [
                'meta' => ['name' => 'welcome_offer', 'category' => 'MARKETING', 'language' => 'en_GB', 'setup_completed' => true],
                'body' => ['text' => 'Hello there', 'samples' => []],
                'footer' => ['text' => ''],
                'buttons' => [],
                'button_mode' => 'none',
            ],
            'body_preview' => 'Hello there',
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::sequence()
                ->push(['Code' => 'OK', 'List' => []], 200)
                ->push(['Code' => 'OK', 'Data' => ['TemplateCode' => '1257583503568572888']], 200),
        ]);

        $ok = app(TemplateWhatsAppService::class)->submitTemplate($template);

        $this->assertTrue($ok);
        $template->refresh();
        $this->assertSame($connected->id, $template->whatsapp_line_id);
        $this->assertSame('1257583503568572888', $template->code);
        $this->assertNotNull($template->synced_at);
    }
}
