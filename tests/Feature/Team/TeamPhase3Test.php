<?php

namespace Tests\Feature\Team;

use App\Domains\Team\Support\TeamPermissions;
use App\Models\Conversation;
use App\Models\TeamMember;
use App\Models\WhatsappLine;
use App\Enums\RecordStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TeamPhase3Test extends TestCase
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

    public function test_team_member_cannot_access_owner_automation_routes(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('chatbot.index'))
            ->assertForbidden();
    }

    public function test_team_member_cannot_access_profile_routes(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('profile.index'))
            ->assertForbidden();
    }

    public function test_global_search_hides_owner_pages_for_team_members(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->getJson(route('search', ['q' => 'Automation']))
            ->assertOk()
            ->assertJsonPath('pages', []);
    }

    public function test_team_member_cannot_open_conversation_on_unassigned_whatsapp_line(): void
    {
        $otherLine = WhatsappLine::query()->create([
            'phone' => '918888888888',
            'display_name' => 'Other Line',
            'status' => RecordStatus::Active,
            'is_default' => false,
        ]);

        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
            'assigned_whatsapp_line_ids' => [$this->testLine->id],
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $otherLine->id,
            'assigned_team_member_id' => $member->id,
            'line_phone' => $otherLine->phone,
            'last_message_at' => now(),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('inbox.show', $conversation))
            ->assertForbidden();
    }

    public function test_team_member_with_module_read_can_create_resources(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), [
                'audience_read' => true,
            ]),
        ]);

        $this->actingAsTeamMember($member)
            ->post(route('audience.lists.store'), [
                'name' => 'Team Created List',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mail_lists', [
            'name' => 'Team Created List',
        ]);
    }

    public function test_team_member_can_login_from_main_login_page(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'email' => 'agent.login@example.test',
            'password' => bcrypt('password'),
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        app(\App\Domains\Team\Services\TeamAccessSyncService::class)->sync($member);

        // Leave tenant context so login flow initializes it like a real request.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->post(route('login.store'), [
            'email' => 'agent.login@example.test',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($member, 'team');
    }

    public function test_team_member_inbox_only_shows_assigned_chats(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
            'assigned_whatsapp_line_ids' => [$this->testLine->id],
        ]);

        $mine = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_team_member_id' => $member->id,
            'contact_name' => 'Mine Chat',
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_team_member_id' => null,
            'contact_name' => 'Other Chat',
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('inbox.index'))
            ->assertOk()
            ->assertSee('Mine Chat')
            ->assertDontSee('Other Chat');

        $this->actingAsTeamMember($member)
            ->get(route('inbox.show', $mine))
            ->assertOk();
    }
}
