<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Models\Admin;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RecentCampaignAdminTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Campaign Admin',
            'email' => 'admin-campaigns@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_recent_campaigns_index_shows_customer_campaign(): void
    {
        Campaign::factory()
            ->completed()
            ->withStats(100, 90, 5, 70)
            ->create([
                'name' => 'Diwali Blast',
                'status' => CampaignStatus::Completed,
                'started_at' => now()->subHour(),
                'completed_at' => now()->subMinutes(10),
            ]);

        // Pure drafts should not clutter the recent list by default.
        Campaign::factory()->draft()->create(['name' => 'Unused Draft']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.recent-campaigns.index'))
            ->assertOk()
            ->assertSee('Recent campaigns')
            ->assertSee('Diwali Blast')
            ->assertSee($this->testTenant->company_name ?: $this->testTenant->name)
            ->assertDontSee('Unused Draft');
    }

    public function test_recent_campaigns_can_filter_by_status(): void
    {
        Campaign::factory()->sending()->create([
            'name' => 'Live Send',
            'started_at' => now(),
        ]);
        Campaign::factory()->completed()->create([
            'name' => 'Old Done',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.recent-campaigns.index', ['status' => 'sending']))
            ->assertOk()
            ->assertSee('Live Send')
            ->assertDontSee('Old Done');
    }
}
