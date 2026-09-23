<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Campaigns\Services\CampaignPresenter;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignPresenterTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->presenter = app(CampaignPresenter::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_index_card_formats_data_correctly(): void
    {
        $audience = MailList::factory()->create(['name' => 'VIP List']);
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign',
            'audience_id' => $audience->id,
            'total_recipients' => 100,
        ]);
        $campaign->load('audience', 'whatsappLine', 'template');

        $card = $this->presenter->indexCard($campaign);

        $this->assertSame('Test Campaign', $card['name']);
        $this->assertSame('VIP List', $card['audience']);
        $this->assertSame('Draft', $card['status_label']);
        $this->assertSame('new', $card['status_variant']);
        $this->assertSame(100, $card['recipients']);
    }

    public function test_index_card_with_no_audience(): void
    {
        $campaign = Campaign::factory()->create(['audience_id' => null]);
        $campaign->load('audience', 'whatsappLine', 'template');

        $card = $this->presenter->indexCard($campaign);

        $this->assertSame('No audience', $card['audience']);
    }

    public function test_index_card_status_variants(): void
    {
        $sending = Campaign::factory()->sending()->create();
        $sending->load('audience', 'whatsappLine', 'template');
        $this->assertSame('sending', $this->presenter->indexCard($sending)['status_variant']);

        $completed = Campaign::factory()->completed()->create();
        $completed->load('audience', 'whatsappLine', 'template');
        $this->assertSame('fd-approved', $this->presenter->indexCard($completed)['status_variant']);

        $paused = Campaign::factory()->paused()->create();
        $paused->load('audience', 'whatsappLine', 'template');
        $this->assertSame('paused', $this->presenter->indexCard($paused)['status_variant']);
    }

    public function test_index_card_delivered_uses_recipient_delivered_status_not_sent_counter(): void
    {
        $campaign = Campaign::factory()->create([
            'total_recipients' => 3,
            'total_delivered' => 3, // stale "API sent" counter — must be ignored
            'total_read' => 1,
            'total_failed' => 0,
            'total_response' => 0,
        ]);
        $campaign->load('audience', 'whatsappLine', 'template');

        \App\Models\CampaignRecipient::factory()->sent()->create(['campaign_id' => $campaign->id]);
        \App\Models\CampaignRecipient::factory()->delivered()->create(['campaign_id' => $campaign->id]);
        \App\Models\CampaignRecipient::factory()->read()->create(['campaign_id' => $campaign->id]);

        $campaign->loadCount([
            'recipients as delivered_recipients_count' => fn ($q) => $q
                ->where('status', \App\Enums\CampaignRecipientStatus::Delivered),
        ]);

        $card = $this->presenter->indexCard($campaign);

        $this->assertSame('1/3', $card['delivered']);
        $this->assertSame('1/3', $card['read']);
    }

    public function test_review_summary_shows_all_data(): void
    {
        $audience = MailList::factory()->create(['name' => 'Target Audience']);
        $campaign = Campaign::factory()->create([
            'name' => 'Review Campaign',
            'audience_id' => $audience->id,
            'total_recipients' => 500,
        ]);
        $campaign->load('audience', 'whatsappLine', 'template');

        $summary = $this->presenter->reviewSummary($campaign);

        $this->assertSame('Review Campaign', $summary['campaign_name']);
        $this->assertSame(500, $summary['recipients_count']);
        $this->assertSame('Target Audience', $summary['audience_name']);
    }

    public function test_step_data_returns_whatsapp_lines_for_step_1(): void
    {
        $data = $this->presenter->stepData(1);

        $this->assertArrayHasKey('whatsappLines', $data);
        $this->assertArrayHasKey('audiences', $data);
    }

    public function test_step_data_returns_templates_for_step_3(): void
    {
        $data = $this->presenter->stepData(3);

        $this->assertArrayHasKey('templates', $data);
        $this->assertArrayHasKey('previewData', $data);
        $this->assertArrayHasKey('costEstimate', $data);
        $this->assertArrayHasKey('recipients', $data['costEstimate']);
    }

    public function test_step_2_audiences_are_newest_first(): void
    {
        $older = MailList::factory()->create(['name' => 'AAA Older', 'created_at' => now()->subDay()]);
        $newer = MailList::factory()->create(['name' => 'ZZZ Newer', 'created_at' => now()]);

        $data = $this->presenter->stepData(2);
        $ids = $data['audiences']->pluck('id')->all();

        $this->assertSame($newer->id, $ids[0]);
        $this->assertContains($older->id, $ids);
        $this->assertTrue(
            array_search($newer->id, $ids, true) < array_search($older->id, $ids, true),
        );
    }

    public function test_step_data_returns_preview_for_step_5(): void
    {
        $data = $this->presenter->stepData(5);

        $this->assertArrayHasKey('previewData', $data);
    }
}
