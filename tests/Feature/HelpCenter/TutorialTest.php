<?php

namespace Tests\Feature\HelpCenter;

use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Models\TeamMember;
use App\Models\TutorialVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TutorialTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        HelpCenterCache::flush();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_owner_can_view_tutorials_index(): void
    {
        TutorialVideo::factory()->create([
            'title' => 'Dashboard Overview',
            'module_name' => 'DASHBOARD',
        ]);

        TutorialVideo::factory()->inactive()->create([
            'title' => 'Hidden Tutorial',
        ]);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index'))
            ->assertOk()
            ->assertSee('Dashboard Overview')
            ->assertDontSee('Hidden Tutorial');
    }

    public function test_owner_can_select_video_by_query_param(): void
    {
        $first = TutorialVideo::factory()->create([
            'title' => 'First Video',
            'module_name' => 'DASHBOARD',
            'sort_order' => 1,
        ]);

        TutorialVideo::factory()->create([
            'title' => 'Second Video',
            'module_name' => 'INBOX',
            'sort_order' => 2,
        ]);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index', ['video_id' => $first->id]))
            ->assertOk()
            ->assertSee('First Video')
            ->assertSee('1 / 2');
    }

    public function test_owner_can_search_tutorials(): void
    {
        TutorialVideo::factory()->create([
            'title' => 'Chatbot Builder',
            'module_name' => 'AUTOMATION',
        ]);

        TutorialVideo::factory()->create([
            'title' => 'Wallet Top Up',
            'module_name' => 'ACCOUNTS',
        ]);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index', ['q' => 'chatbot']))
            ->assertOk()
            ->assertSee('Chatbot Builder')
            ->assertDontSee('Wallet Top Up');
    }

    public function test_team_member_cannot_access_tutorials(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('tutorials.index'))
            ->assertForbidden();
    }
}
