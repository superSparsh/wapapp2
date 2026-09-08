<?php

declare(strict_types=1);

namespace Tests\Unit\Drip;

use App\Domains\Drip\Services\DripStatPresenter;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripStatPresenterTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private DripStatPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->presenter = new DripStatPresenter();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_gauge_metrics_calculated_correctly(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')->count(10)->entered()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')->count(7)->completed()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')->count(2)->dropped()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')->count(1)->error()->create();

        $metrics = $this->presenter->gaugeMetrics($campaign);

        $this->assertSame(20, $metrics['total']);
        $this->assertSame(10, $metrics['sent']);
        $this->assertSame(7, $metrics['delivered']);
        $this->assertSame(7, $metrics['read']);
        $this->assertSame(1, $metrics['failed']);
        $this->assertSame(2, $metrics['dropped']);
        $this->assertSame('50%', $metrics['sent_pct']);
        $this->assertSame('35%', $metrics['delivered_pct']);
        $this->assertSame('5%', $metrics['failed_pct']);
    }

    public function test_gauge_metrics_empty_campaign(): void
    {
        $campaign = DripCampaign::factory()->create();

        $metrics = $this->presenter->gaugeMetrics($campaign);

        $this->assertSame(0, $metrics['total']);
        $this->assertSame('0%', $metrics['sent_pct']);
        $this->assertSame('0%', $metrics['delivered_pct']);
        $this->assertSame('0%', $metrics['read_pct']);
        $this->assertSame('0%', $metrics['failed_pct']);
    }

    public function test_detail_log_paginates(): void
    {
        $campaign = DripCampaign::factory()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')->count(25)->create();

        $result = $this->presenter->detailLog($campaign, perPage: 10);

        $this->assertSame(25, $result->total());
        $this->assertSame(10, $result->perPage());
        $this->assertCount(10, $result->items());
    }

    public function test_detail_log_ordered_by_created_at_desc(): void
    {
        $campaign = DripCampaign::factory()->create();

        $old = DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('910000000001')->create();
        $old->forceFill(['created_at' => now()->subDays(5)])->save();

        $new = DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('910000000002')->create();

        $result = $this->presenter->detailLog($campaign);

        $this->assertSame('910000000002', $result->first()->contact_phone);
    }

    public function test_csv_export_generates_correct_rows(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('911111111111')->forNode('n1', 'welcome')->entered()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('912222222222')->forNode('n2', 'template')->completed()->create();

        $response = $this->presenter->exportCsv($campaign);

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $lines = array_filter(explode("\n", trim($content)));

        // Header + 2 data rows
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('Contact Phone', $lines[0]);
        // Rows are ordered by created_at desc, both phones should be present
        $dataRows = $lines[1] . ' ' . $lines[2];
        $this->assertStringContainsString('911111111111', $dataRows);
        $this->assertStringContainsString('912222222222', $dataRows);
    }

    public function test_csv_export_empty_has_header_only(): void
    {
        $campaign = DripCampaign::factory()->create();

        $response = $this->presenter->exportCsv($campaign);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $lines = array_filter(explode("\n", trim($content)));

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Contact Phone', $lines[0]);
    }
}
