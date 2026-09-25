<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignStatisticsTest extends TestCase
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

    // ─── Overview ─────────────────────────────────────────────────────────

    public function test_statistics_overview_renders(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics', $campaign))
            ->assertOk()
            ->assertViewIs('campaigns.statistics')
            ->assertViewHas('campaign')
            ->assertViewHas('metrics');
    }

    public function test_statistics_overview_has_correct_metrics(): void
    {
        $campaign = Campaign::factory()->create();

        CampaignRecipient::factory()->for($campaign)->delivered()->count(5)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(2)->create();
        CampaignRecipient::factory()->for($campaign)->read()->count(3)->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics', $campaign))
            ->assertOk()
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total'] === 10
                    && $metrics['delivered'] === 5
                    && $metrics['failed'] === 2
                    && $metrics['read'] === 3;
            });
    }

    public function test_statistics_zero_recipients_returns_all_zero(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics', $campaign))
            ->assertOk()
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total'] === 0
                    && $metrics['delivered_pct'] === '0%'
                    && $metrics['failed_pct'] === '0%';
            });
    }

    // ─── Detail ───────────────────────────────────────────────────────────

    public function test_statistics_detail_renders(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->count(5)->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.detail', $campaign))
            ->assertOk()
            ->assertViewIs('campaigns.detail')
            ->assertViewHas('campaign')
            ->assertViewHas('recipients');
    }

    public function test_statistics_detail_filters_by_status(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->count(3)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(2)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.detail', ['bulkCampaign' => $campaign, 'status' => 'delivered']))
            ->assertOk();

        $response->assertViewHas('recipients', function ($recipients) {
            return $recipients->total() === 3;
        });
    }

    public function test_failed_detail_shows_failure_reason(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->failed()->create([
            'failure_reason' => 'Template rejected by provider',
            'failed_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.detail', ['bulkCampaign' => $campaign, 'status' => 'failed']))
            ->assertOk()
            ->assertSee('Reason')
            ->assertSee('Failed At')
            ->assertSee('Template rejected by provider');
    }

    public function test_delivered_detail_hides_failure_columns(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.detail', ['bulkCampaign' => $campaign, 'status' => 'delivered']))
            ->assertOk()
            ->assertSee('Delivered At')
            ->assertDontSee('Reason')
            ->assertDontSee('Failed At');
    }

    public function test_statistics_detail_paginates(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->count(25)->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.detail', $campaign))
            ->assertOk()
            ->assertViewHas('recipients', function ($recipients) {
                return $recipients->total() === 25
                    && $recipients->perPage() === 10;
            });
    }

    // ─── Export ───────────────────────────────────────────────────────────

    public function test_export_downloads_csv(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->count(3)->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.export', $campaign))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_export_respects_status_filter(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->for($campaign)->delivered()->count(2)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(1)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('campaigns.statistics.export', ['bulkCampaign' => $campaign, 'status' => 'failed']));

        $response->assertOk();

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Failed', $csv);
        $this->assertSame(2, substr_count($csv, "\n")); // header + 1 failed row
    }

    public function test_export_report_downloads_summary_and_recipients(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Report Campaign']);
        CampaignRecipient::factory()->for($campaign)->delivered()->count(2)->create();
        CampaignRecipient::factory()->for($campaign)->failed()->count(1)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('campaigns.export-report', $campaign));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Campaign Report — Summary', $csv);
        $this->assertStringContainsString('Campaign Report — Recipients', $csv);
        $this->assertStringContainsString('Report Campaign', $csv);
        $this->assertStringContainsString('Total Campaign Cost (INR)', $csv);
    }
}
