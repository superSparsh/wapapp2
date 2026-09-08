<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Domains\Campaigns\Services\CampaignSendService;
use App\Enums\CampaignRecipientStatus;
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

    public function test_launch_uses_cams_mass_message_api(): void
    {
        Queue::fake();
        config(['campaigns.mass_threshold' => 2]);
        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'GroupId' => 'grp-mass-1',
            ], 200),
        ]);

        $line = WhatsappLine::factory()->connected()->create();
        $template = Template::factory()->create(['code' => 'promo_offer']);
        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $line->id,
            'template_id' => $template->id,
            'template_variables' => ['offer' => '20%'],
        ]);

        CampaignRecipient::factory()->for($campaign)->pending()->create(['contact_phone' => '919811111111']);
        CampaignRecipient::factory()->for($campaign)->pending()->create(['contact_phone' => '919822222222']);

        app(CampaignSendService::class)->queueCampaign($campaign);

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'Action=SendChatappMassMessage')
                && str_contains($url, 'TemplateCode=promo_offer')
                && str_contains($url, 'SenderList.1.To')
                && str_contains($url, 'SenderList.2.To');
        });

        Queue::assertNothingPushed();

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
        $this->assertSame(2, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', CampaignRecipientStatus::Sent)->count());
        $this->assertSame('grp-mass-1', CampaignRecipient::query()->where('campaign_id', $campaign->id)->value('message_id'));
    }

    public function test_launch_uses_simple_api_jobs_when_below_mass_threshold(): void
    {
        Queue::fake();
        config(['campaigns.mass_threshold' => 50]);

        $line = WhatsappLine::factory()->connected()->create();
        $template = Template::factory()->create();
        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $line->id,
            'template_id' => $template->id,
        ]);
        CampaignRecipient::factory()->for($campaign)->pending()->count(2)->create();

        app(CampaignSendService::class)->queueCampaign($campaign);

        Queue::assertPushed(SendCampaignRecipientJob::class, 2);
        Http::assertNothingSent();
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    public function test_launch_falls_back_to_per_recipient_jobs_when_cams_unavailable(): void
    {
        Queue::fake();
        config(['campaigns.mass_threshold' => 1]);

        $line = WhatsappLine::factory()->create(['alibaba_cust_space_id' => null]);
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
