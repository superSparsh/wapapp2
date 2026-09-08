<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Campaigns\Services\CampaignStatsService;
use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignStatsServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new CampaignStatsService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_gauge_metrics_returns_correct_counts(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->count(5)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(2)->create();
        CampaignRecipient::factory()->for($campaign)->read()->count(3)->create();
        CampaignRecipient::factory()->for($campaign)->pending()->count(10)->create();

        $metrics = $this->service->gaugeMetrics($campaign);

        $this->assertSame(20, $metrics['total']);
        $this->assertSame(8, $metrics['delivered']);
        $this->assertSame(2, $metrics['failed']);
        $this->assertSame(3, $metrics['read']);
        $this->assertSame(10, $metrics['pending']);
    }

    public function test_gauge_metrics_calculates_percentages(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->count(8)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(2)->create();

        $metrics = $this->service->gaugeMetrics($campaign);

        $this->assertSame('80%', $metrics['delivered_pct']);
        $this->assertSame('20%', $metrics['failed_pct']);
    }

    public function test_gauge_metrics_zero_recipients_returns_all_zero(): void
    {
        $campaign = Campaign::factory()->create();

        $metrics = $this->service->gaugeMetrics($campaign);

        $this->assertSame(0, $metrics['total']);
        $this->assertSame(0, $metrics['delivered']);
        $this->assertSame('0%', $metrics['delivered_pct']);
        $this->assertSame('0%', $metrics['failed_pct']);
        $this->assertSame('0%', $metrics['read_pct']);
    }

    public function test_recipient_log_paginates(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->count(25)->create();

        $result = $this->service->recipientLog($campaign, perPage: 10);

        $this->assertSame(25, $result->total());
        $this->assertSame(10, $result->perPage());
        $this->assertCount(10, $result->items());
    }

    public function test_recipient_log_filters_by_status(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->count(5)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(3)->create();

        $result = $this->service->recipientLog($campaign, status: 'delivered');

        $this->assertSame(5, $result->total());
    }

    public function test_recipient_log_returns_all_when_no_filter(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->count(5)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(3)->create();

        $result = $this->service->recipientLog($campaign);

        $this->assertSame(8, $result->total());
    }

    public function test_export_csv_streams(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->count(3)->create();

        $response = $this->service->exportCsv($campaign);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
    }

    public function test_dashboard_stats_aggregates(): void
    {
        Campaign::factory()->withStats(100, 80, 10, 70)->create();
        Campaign::factory()->withStats(200, 160, 20, 140)->create();

        $stats = $this->service->dashboardStats();

        $this->assertSame(2, $stats['total_campaigns']);
        $this->assertSame(300, $stats['total_sent']);
        $this->assertSame(240, $stats['total_delivered']);
    }
}
