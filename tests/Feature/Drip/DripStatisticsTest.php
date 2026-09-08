<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Enums\ChatbotFlowStatAction;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripStatisticsTest extends TestCase
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

    public function test_statistics_overview_renders_gauge_metrics(): void
    {
        $campaign = DripCampaign::factory()->create();

        // Create stats with various actions
        DripCampaignStat::factory()->count(5)->for($campaign, 'dripCampaign')->entered()->create();
        DripCampaignStat::factory()->count(3)->for($campaign, 'dripCampaign')->completed()->create();
        DripCampaignStat::factory()->count(1)->for($campaign, 'dripCampaign')->error()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics', $campaign))
            ->assertOk()
            ->assertViewIs('automation.drip-statistics')
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total'] === 9
                    && $metrics['sent'] === 5
                    && $metrics['delivered'] === 3
                    && $metrics['failed'] === 1;
            });
    }

    public function test_statistics_overview_empty_campaign_shows_zeros(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics', $campaign))
            ->assertOk()
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total'] === 0
                    && $metrics['sent_pct'] === '0%'
                    && $metrics['failed_pct'] === '0%';
            });
    }

    public function test_statistics_detail_renders_paginated_log(): void
    {
        $campaign = DripCampaign::factory()->create();
        DripCampaignStat::factory()->count(15)->for($campaign, 'dripCampaign')->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics.detail', $campaign))
            ->assertOk()
            ->assertViewIs('automation.drip-statistics-detail')
            ->assertViewHas('messages', function ($messages) {
                return $messages->total() === 15;
            });
    }

    public function test_statistics_export_returns_csv(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900001')
            ->entered()
            ->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900002')
            ->completed()
            ->create();

        $response = $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics.export', $campaign));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Contact Phone', $content);
        $this->assertStringContainsString('919999900001', $content);
        $this->assertStringContainsString('919999900002', $content);
        $this->assertStringContainsString('entered', $content);
        $this->assertStringContainsString('completed', $content);
    }

    public function test_statistics_export_empty_campaign_returns_header_only(): void
    {
        $campaign = DripCampaign::factory()->create();

        $response = $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics.export', $campaign));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        // Should contain only the header row
        $this->assertStringContainsString('Contact Phone', $content);
        $this->assertStringNotContainsString('91999', $content);
    }
}
