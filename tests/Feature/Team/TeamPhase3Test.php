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

    public function test_team_member_can_open_conversation_on_assigned_whatsapp_line(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
            'assigned_whatsapp_line_ids' => [$this->testLine->id],
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_team_member_id' => $member->id,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('inbox.show', $conversation))
            ->assertOk();
    }
}
