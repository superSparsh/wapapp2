<?php

namespace Tests\Feature\Team;

use App\Domains\Team\Support\TeamPermissions;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\Conversation;
use App\Models\ManagerMemberAssignment;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TeamMemberTest extends TestCase
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

    public function test_owner_can_view_team_index(): void
    {
        TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Ravi',
            'last_name' => 'Kumar',
        ]);

        $this->actingAsTenantUser()
            ->get(route('my-team.index'))
            ->assertOk()
            ->assertSee('Ravi Kumar');
    }

    public function test_owner_can_create_team_member_and_redirect_to_roles(): void
    {
        $response = $this->actingAsTenantUser()
            ->post(route('my-team.store'), [
                'first_name' => 'Asha',
                'last_name' => 'Singh',
                'phone' => '9876543210',
                'email' => 'asha@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => TeamMemberRole::Member->value,
            ])
            ->assertRedirect();

        $member = TeamMember::query()->where('email', 'asha@example.test')->first();

        $this->assertNotNull($member);
        $this->assertSame('919876543210', $member->phone);
        $response->assertRedirect(route('my-team.roles', $member));
    }

    public function test_owner_can_update_permissions_and_manager_assignments(): void
    {
        $manager = TeamMember::factory()->manager()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Neha',
            'last_name' => 'Verma',
        ]);

        $this->actingAsTenantUser()
            ->put(route('my-team.roles.update', $manager), [
                'permissions' => [
                    'template_read' => '1',
                    'audience_read' => '0',
                    'campaign_read' => '1',
                    'inbox_read' => '1',
                ],
                'member_uuids' => [$member->uuid],
            ])
            ->assertRedirect(route('my-team.index'));

        $manager->refresh();

        $this->assertTrue(TeamPermissions::isEnabled($manager->permissions, 'template_read'));
        $this->assertFalse(TeamPermissions::isEnabled($manager->permissions, 'audience_read'));
        $this->assertDatabaseHas('manager_member_assignments', [
            'parent_user_id' => $this->testUser->id,
            'manager_id' => $manager->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_owner_can_toggle_team_member_status(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'status' => RecordStatus::Active,
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('my-team.status', $member))
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertSame(RecordStatus::Inactive, $member->fresh()->status);
    }

    public function test_team_list_shows_assigned_conversation_count(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Count',
            'last_name' => 'Check',
        ]);

        Conversation::factory()->count(2)->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_team_member_id' => $member->id,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('my-team.index'))
            ->assertOk()
            ->assertSee('Count Check')
            ->assertSee('>2<', false);
    }

    public function test_team_member_cannot_access_owner_team_pages(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('my-team.index'))
            ->assertForbidden();
    }
}
