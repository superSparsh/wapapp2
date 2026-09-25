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
            'module_name' => 'Module 1: Dashboard',
        ]);

        // Old placeholder seed group — must stay hidden.
        TutorialVideo::factory()->create([
            'title' => 'Inbox Basics',
            'module_name' => 'INBOX',
        ]);

        TutorialVideo::factory()->inactive()->create([
            'title' => 'Hidden Tutorial',
            'module_name' => 'Module 2: Inbox',
        ]);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index'))
            ->assertOk()
            ->assertSee('Dashboard Overview')
            ->assertSee('Module 1: Dashboard')
            ->assertSee('Note: These are tutorials from the previous WapApp design.')
            ->assertDontSee('Inbox Basics')
            ->assertDontSee('Hidden Tutorial');
    }

    public function test_owner_can_select_video_by_query_param(): void
    {
        $first = TutorialVideo::factory()->create([
            'title' => 'First Video',
            'module_name' => 'Module 1: Dashboard',
            'sort_order' => 1,
        ]);

        $second = TutorialVideo::factory()->create([
            'title' => 'Second Video',
            'module_name' => 'Module 2: Inbox',
            'sort_order' => 2,
        ]);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index', ['video_id' => $first->id]))
            ->assertOk()
            ->assertSee('First Video')
            ->assertSee('1 / 2')
            ->assertSee('Copy Link')
            ->assertSee(route('tutorials.index', ['video_id' => $second->id], false), false);
    }

    public function test_next_link_opens_following_video(): void
    {
        $first = TutorialVideo::factory()->create([
            'title' => 'First Video',
            'module_name' => 'Module 1: Dashboard',
            'sort_order' => 1,
        ]);

        $second = TutorialVideo::factory()->create([
            'title' => 'Second Video',
            'module_name' => 'Module 2: Inbox',
            'sort_order' => 2,
        ]);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index', ['video_id' => $first->id]))
            ->assertOk()
            ->assertSee('href="'.route('tutorials.index', ['video_id' => $second->id]).'"', false);

        $this->actingAsTenantUser()
            ->get(route('tutorials.index', ['video_id' => $second->id]))
            ->assertOk()
            ->assertSee('Second Video')
            ->assertSee('2 / 2');
    }

    public function test_owner_can_search_tutorials(): void
    {
        TutorialVideo::factory()->create([
            'title' => 'Chatbot Builder',
            'module_name' => 'Module 3: Automation - Sub-module 1: Chatbot',
        ]);

        TutorialVideo::factory()->create([
            'title' => 'Wallet Top Up',
            'module_name' => 'Module 10: Accounts',
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
