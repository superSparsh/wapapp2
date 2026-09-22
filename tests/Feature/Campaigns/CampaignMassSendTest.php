<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Domains\Campaigns\Services\CampaignSendService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignMassSendTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.ap-southeast-1.aliyuncs.com',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_launch_always_uses_simple_api_jobs_not_mass_api(): void
    {
        Queue::fake();

        $line = WhatsappLine::factory()->connected()->create();
        $template = Template::factory()->create(['code' => 'promo_offer']);
        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $line->id,
            'template_id' => $template->id,
            'template_variables' => ['offer' => '20%'],
        ]);

        CampaignRecipient::factory()->for($campaign)->pending()->count(5)->create();

        app(CampaignSendService::class)->queueCampaign($campaign);

        Queue::assertPushed(SendCampaignRecipientJob::class, 5);
        Http::assertNothingSent();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'Action=SendChatappMassMessage'));
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    public function test_launch_queues_one_job_per_pending_recipient(): void
    {
        Queue::fake();

        $line = WhatsappLine::factory()->connected()->create();
        $template = Template::factory()->create();
        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $line->id,
            'template_id' => $template->id,
        ]);
        CampaignRecipient::factory()->for($campaign)->pending()->count(2)->create();

        app(CampaignSendService::class)->queueCampaign($campaign);

        Queue::assertPushed(SendCampaignRecipientJob::class, 2);
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }
}
