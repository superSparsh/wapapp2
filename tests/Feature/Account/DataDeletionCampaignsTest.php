<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Domains\Account\Services\DataDeletionService;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DataDeletionCampaignsTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_export_campaigns_includes_old_campaigns(): void
    {
        $old = Campaign::factory()->create(['name' => 'Old Promo']);
        Campaign::query()->whereKey($old->id)->update(['created_at' => now()->subMonths(4)]);

        $recent = Campaign::factory()->create(['name' => 'Recent Promo']);
        Campaign::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(2)]);

        $service = app(DataDeletionService::class);
        $method = new ReflectionMethod($service, 'exportCampaigns');
        $method->setAccessible(true);
        $payload = $method->invoke($service, now()->subMonths(3));

        $this->assertSame(1, $payload['count']);
        $this->assertSame($old->id, $payload['items'][0]['id']);
        $this->assertSame('Old Promo', $payload['items'][0]['name']);
    }

    public function test_delete_campaigns_removes_old_campaigns_and_recipients(): void
    {
        $old = Campaign::factory()->create();
        Campaign::query()->whereKey($old->id)->update(['created_at' => now()->subMonths(4)]);
        CampaignRecipient::factory()->create([
            'campaign_id' => $old->id,
        ]);
        $recent = Campaign::factory()->create();
        Campaign::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(2)]);

        $service = app(DataDeletionService::class);
        $method = new ReflectionMethod($service, 'deleteCampaigns');
        $method->setAccessible(true);
        $deleted = $method->invoke($service, now()->subMonths(3));

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('campaigns', ['id' => $old->id]);
        $this->assertDatabaseMissing('campaign_recipients', ['campaign_id' => $old->id]);
        $this->assertDatabaseHas('campaigns', ['id' => $recent->id]);
    }

    public function test_request_export_json_includes_campaigns_module(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Archived']);
        Campaign::query()->whereKey($campaign->id)->update(['created_at' => now()->subYears(2)]);

        /** @var User $user */
        $user = $this->testUser;

        $export = app(DataDeletionService::class)->requestExport('1_year', ['campaigns'], $user);
        $export->refresh();

        $this->assertNotNull($export->file_path);
        $json = json_decode(Storage::disk('local')->get($export->file_path), true);
        $this->assertArrayHasKey('campaigns', $json['modules'] ?? []);
        $campaigns = $json['modules']['campaigns'];
        $this->assertGreaterThanOrEqual(1, (int) ($campaigns['count'] ?? 0));
    }
}
