<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    private string $serviceToken = 'default-campaign-service-secret-token';
    private string $tenantId = 'tenant_camp_test_123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service-auth.token' => $this->serviceToken]);
        app(TenantContext::class)->setContext($this->tenantId, 1, 1, 'user-uuid', 'tm-uuid', 'Admin', 'Admin', false);
    }

    private function authHeaders(array $extra = []): array
    {
        return array_merge([
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => $this->tenantId,
            'X-Actor-User-Id' => '1',
            'X-Actor-Team-Member-Id' => '1',
            'Accept' => 'application/json',
        ], $extra);
    }

    public function test_health_check_returns_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('service', 'campaign-service');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/campaigns');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'SERVICE_UNAUTHORIZED');
    }

    public function test_missing_tenant_header_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/campaigns', [
            'X-Service-Token' => $this->serviceToken,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('code', 'TENANT_HEADER_REQUIRED');
    }

    public function test_create_and_list_campaigns(): void
    {
        $response = $this->postJson('/api/v1/campaigns', [
            'name' => 'Black Friday Blast',
            'whatsapp_line_id' => 10,
            'template_id' => 20,
            'template_variables' => ['template_code' => 'bf_promo_2026', 'discount' => '20%'],
            'recipients' => [
                ['contact_phone' => '919876543210', 'variable_values' => ['name' => 'Alice']],
                ['contact_phone' => '919876543211', 'variable_values' => ['name' => 'Bob']],
            ],
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('campaign.name', 'Black Friday Blast')
            ->assertJsonPath('campaign.total_recipients', 2)
            ->assertJsonPath('campaign.status', 'draft');

        $list = $this->getJson('/api/v1/campaigns', $this->authHeaders());
        $list->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Black Friday Blast');
    }

    public function test_campaign_lifecycle_actions(): void
    {
        Queue::fake();

        $campaign = Campaign::query()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Summer Sale',
            'status' => CampaignStatus::Draft,
            'whatsapp_line_id' => 5,
            'template_id' => 12,
            'template_variables' => [
                'template_code' => 'summer_sale',
                'line_phone' => '918888888888',
                'cust_space_id' => 'SP123',
                'language' => 'en_GB',
            ],
        ]);

        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_phone' => '919999999999',
            'status' => CampaignRecipientStatus::Pending,
        ]);

        // Launch (1 recipient < mass_threshold → simple jobs)
        $launch = $this->postJson("/api/v1/campaigns/{$campaign->uuid}/launch", [], $this->authHeaders());
        $launch->assertOk()
            ->assertJsonPath('campaign.status', 'sending');

        Queue::assertPushed(\App\Jobs\SendCampaignRecipientJob::class, 1);

        // Toggle pause
        $pause = $this->postJson("/api/v1/campaigns/{$campaign->uuid}/toggle", [], $this->authHeaders());
        $pause->assertOk()
            ->assertJsonPath('campaign.status', 'paused');

        // Toggle resume
        $resume = $this->postJson("/api/v1/campaigns/{$campaign->uuid}/toggle", [], $this->authHeaders());
        $resume->assertOk()
            ->assertJsonPath('campaign.status', 'sending');

        // Duplicate
        $dup = $this->postJson("/api/v1/campaigns/{$campaign->uuid}/duplicate", [], $this->authHeaders());
        $dup->assertStatus(201)
            ->assertJsonPath('campaign.name', 'Summer Sale (Copy)');
    }

    public function test_launch_uses_mass_api_when_recipients_meet_threshold(): void
    {
        Queue::fake();
        config([
            'campaigns.mass_threshold' => 2,
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => \Illuminate\Support\Facades\Http::response([
                'Code' => 'OK',
                'GroupId' => 'grp-svc-1',
            ], 200),
        ]);

        $campaign = Campaign::query()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Mass Blast',
            'status' => CampaignStatus::Draft,
            'whatsapp_line_id' => 1,
            'template_id' => 1,
            'template_variables' => [
                'template_code' => 'blast_tpl',
                'line_phone' => '918888888888',
                'cust_space_id' => 'SP999',
                'language' => 'en_GB',
            ],
        ]);

        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_phone' => '919111111111',
            'status' => CampaignRecipientStatus::Pending,
        ]);
        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_phone' => '919222222222',
            'status' => CampaignRecipientStatus::Pending,
        ]);

        $this->postJson("/api/v1/campaigns/{$campaign->uuid}/launch", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('campaign.status', 'completed');

        Queue::assertNothingPushed();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'Action=SendChatappMassMessage'));
        $this->assertSame(2, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', CampaignRecipientStatus::Sent)->count());
    }

    public function test_campaign_statistics_and_recipients(): void
    {
        $campaign = Campaign::query()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Stats Test Campaign',
            'status' => CampaignStatus::Completed,
            'whatsapp_line_id' => 1,
            'template_id' => 1,
            'total_recipients' => 3,
            'total_delivered' => 2,
            'total_failed' => 1,
        ]);

        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_phone' => '919000000001',
            'status' => CampaignRecipientStatus::Delivered,
        ]);
        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_phone' => '919000000002',
            'status' => CampaignRecipientStatus::Delivered,
        ]);
        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_phone' => '919000000003',
            'status' => CampaignRecipientStatus::Failed,
            'failure_reason' => 'User not on WhatsApp',
        ]);

        $stats = $this->getJson("/api/v1/campaigns/{$campaign->uuid}/statistics", $this->authHeaders());
        $stats->assertOk()
            ->assertJsonPath('metrics.total', 3)
            ->assertJsonPath('metrics.delivered', 2)
            ->assertJsonPath('metrics.failed', 1);

        $recipients = $this->getJson("/api/v1/campaigns/{$campaign->uuid}/recipients", $this->authHeaders());
        $recipients->assertOk()
            ->assertJsonCount(3, 'items');

        $cost = $this->getJson("/api/v1/campaigns/{$campaign->uuid}/cost?category=MARKETING", $this->authHeaders());
        $cost->assertOk()
            ->assertJsonPath('recipients', 3)
            ->assertJsonPath('currency', 'INR');
    }

    public function test_csv_recipient_import(): void
    {
        $campaign = Campaign::query()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'CSV Import Test',
            'status' => CampaignStatus::Draft,
            'whatsapp_line_id' => 1,
            'template_id' => 1,
        ]);

        $csvContent = "phone,name,coupon\n919111111111,Charlie,SAVE10\n919222222222,David,SAVE20\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->postJson("/api/v1/campaigns/{$campaign->uuid}/import-recipients", [
            'file' => $file,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('skipped', 0);

        $this->assertSame(2, CampaignRecipient::query()->where('campaign_id', $campaign->id)->count());
    }
}
