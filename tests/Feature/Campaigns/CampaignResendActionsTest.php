<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MailList;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignResendActionsTest extends TestCase
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

    public function test_create_campaign_from_failed_schedule_creates_draft(): void
    {
        Queue::fake();

        $template = Template::factory()->create();
        $source = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'whatsapp_line_id' => $this->testLine->id,
            'template_id' => $template->id,
            'total_failed' => 2,
        ]);

        CampaignRecipient::factory()->for($source)->failed()->count(2)->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.resend-failed', $source), [
                'list_name' => 'Failed List',
                'campaign_name' => 'Failed Resend Campaign',
                'send_option' => 'schedule',
                'mode' => 'create',
            ])
            ->assertRedirect();

        $list = MailList::query()->where('name', 'Failed List')->first();
        $this->assertNotNull($list);
        $this->assertSame(2, Contact::query()->where('mail_list_id', $list->id)->count());

        $newCampaign = Campaign::query()->where('name', 'Failed Resend Campaign')->first();
        $this->assertNotNull($newCampaign);
        $this->assertSame(CampaignStatus::Draft, $newCampaign->status);
        $this->assertSame($list->id, $newCampaign->audience_id);
        $this->assertSame(2, (int) $newCampaign->total_recipients);
        Queue::assertNothingPushed();
    }

    public function test_create_campaign_from_failed_send_now_queues_jobs(): void
    {
        Queue::fake();

        $template = Template::factory()->create(['code' => 'promo_offer']);
        $source = Campaign::factory()->create([
            'status' => CampaignStatus::Failed,
            'whatsapp_line_id' => $this->testLine->id,
            'template_id' => $template->id,
            'total_failed' => 1,
        ]);

        CampaignRecipient::factory()->for($source)->failed()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.resend-failed', $source), [
                'list_name' => 'Failed Now List',
                'campaign_name' => 'Failed Now Campaign',
                'send_option' => 'now',
                'mode' => 'create',
            ])
            ->assertRedirect();

        $newCampaign = Campaign::query()->where('name', 'Failed Now Campaign')->first();
        $this->assertNotNull($newCampaign);
        $this->assertSame(CampaignStatus::Sending, $newCampaign->status);
        Queue::assertPushed(SendCampaignRecipientJob::class, 1);
    }

    public function test_inplace_resend_failed_requeues_on_same_campaign(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'whatsapp_line_id' => $this->testLine->id,
            'total_failed' => 1,
        ]);

        CampaignRecipient::factory()->for($campaign)->failed()->create();

        $this->actingAsTenantUser()
            ->postJson(route('campaigns.resend-failed', $campaign), [
                'mode' => 'inplace',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'resent' => 1]);

        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
        $this->assertSame(
            CampaignRecipientStatus::Pending,
            $campaign->recipients()->first()->status,
        );
        Queue::assertPushed(SendCampaignRecipientJob::class, 1);
    }

    public function test_resend_opt_in_endpoint_returns_counts(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'whatsapp_line_id' => $this->testLine->id,
            'total_failed' => 1,
        ]);

        $contact = Contact::factory()->create(['phone' => '919999888877']);
        CampaignRecipient::factory()->for($campaign)->failed()->create([
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.resend-opt-in', $campaign))
            ->assertRedirect(route('campaigns.statistics', $campaign));
    }

    public function test_pause_and_resume_labels_on_statistics_page(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics', $campaign))
            ->assertOk()
            ->assertSee('Pause')
            ->assertSee(route('campaigns.toggle', $campaign), false);

        $campaign->update(['status' => CampaignStatus::Paused]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.statistics', $campaign))
            ->assertOk()
            ->assertSee('Resume');
    }
}
