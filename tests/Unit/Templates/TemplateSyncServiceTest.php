<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplateSyncService;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateSyncServiceTest extends TestCase
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

    public function test_coded_sync_updates_category_when_meta_reclassifies(): void
    {
        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
        ]);

        $template = Template::factory()->create([
            'whatsapp_line_id' => $line->id,
            'code' => '1257583503568572416',
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::Approved,
            'payload' => ['meta' => ['category' => 'MARKETING', 'setup_completed' => true]],
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'data' => [
                    'auditStatus' => 'pass',
                    'category' => 'UTILITY',
                    'templateCode' => '1257583503568572416',
                ],
            ], 200),
        ]);

        $result = app(TemplateSyncService::class)->syncCodedDetailsBatch(10);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['category_updates']);

        $template->refresh();
        $this->assertSame('UTILITY', $template->category);
        $this->assertSame('UTILITY', $template->payload['meta']['category'] ?? null);
        $this->assertSame(TemplateStatus::Approved, $template->status);

        $this->assertDatabaseHas('template_status_logs', [
            'template_id' => $template->id,
            'reason' => 'Category updated to UTILITY',
        ]);
    }

    public function test_coded_sync_does_not_clobber_lto_when_remote_is_marketing(): void
    {
        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
        ]);

        $template = Template::factory()->create([
            'whatsapp_line_id' => $line->id,
            'code' => '1257583503568572417',
            'language' => 'en_GB',
            'category' => 'LIMITED_TIME_OFFER',
            'status' => TemplateStatus::Approved,
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'data' => [
                    'auditStatus' => 'pass',
                    'category' => 'MARKETING',
                    'templateCode' => '1257583503568572417',
                ],
            ], 200),
        ]);

        $result = app(TemplateSyncService::class)->syncCodedDetailsBatch(10);

        $this->assertSame(0, $result['category_updates']);
        $this->assertSame('LIMITED_TIME_OFFER', $template->fresh()->category);
    }

    public function test_list_by_name_reads_nested_data_list_template_and_approves(): void
    {
        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => '100000430113',
        ]);

        $template = Template::factory()->create([
            'whatsapp_line_id' => $line->id,
            'name' => 'hello_world',
            'code' => null,
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::PendingReview,
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'Data' => [
                    'ListTemplate' => [[
                        'AuditStatus' => 'PASS',
                        'TemplateCode' => '1257583503568572999',
                        'Category' => 'MARKETING',
                        'TemplateName' => 'hello_world',
                    ]],
                ],
            ], 200),
        ]);

        app(TemplateSyncService::class)->syncFirstPending();

        $template->refresh();
        $this->assertSame(TemplateStatus::Approved, $template->status);
        $this->assertSame('1257583503568572999', $template->code);
    }

    public function test_map_audit_status_is_case_insensitive(): void
    {
        $service = app(TemplateSyncService::class);

        [$status] = $service->mapAuditStatus('PASS');
        $this->assertSame(TemplateStatus::Approved, $status);

        [$status] = $service->mapAuditStatus('Fail', 'bad sample');
        $this->assertSame(TemplateStatus::Rejected, $status);

        [$status] = $service->mapAuditStatus('Auditing');
        $this->assertSame(TemplateStatus::PendingReview, $status);
    }
}
