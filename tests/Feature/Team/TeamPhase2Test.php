<?php

namespace Tests\Feature\Team;

use App\Domains\Team\Support\TeamPermissions;
use App\Enums\RecordStatus;
use App\Models\ManagerMemberAssignment;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TeamPhase2Test extends TestCase
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

    public function test_team_member_without_inbox_permission_cannot_access_inbox(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => TeamPermissions::defaults(),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('inbox.index'))
            ->assertForbidden();
    }

    public function test_team_member_with_inbox_permission_can_access_inbox(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('inbox.index'))
            ->assertOk();
    }

    public function test_team_member_dashboard_redirects_to_first_allowed_module(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['campaign_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('dashboard'))
            ->assertRedirect(route('campaigns.index'));
    }

    public function test_manager_sees_only_assigned_members(): void
    {
        $manager = TeamMember::factory()->manager()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $assigned = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Assigned',
            'last_name' => 'Member',
        ]);

        TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Hidden',
            'last_name' => 'Member',
        ]);

        ManagerMemberAssignment::query()->create([
            'parent_user_id' => $this->testUser->id,
            'manager_id' => $manager->id,
            'member_id' => $assigned->id,
        ]);

        $this->actingAsManager($manager)
            ->get(route('manager.team.index'))
            ->assertOk()
            ->assertSee('Assigned Member')
            ->assertDontSee('Hidden Member');
    }

    public function test_manager_can_import_csv_members(): void
    {
        $manager = TeamMember::factory()->manager()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $csv = "first_name,last_name,email,phone_number,password,permissions\n";
        $csv .= "CSV,User,csvuser@example.test,9123456789,password,\"inbox_read=yes\"\n";

        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $this->actingAsManager($manager)
            ->post(route('manager.team.import.store'), ['file' => $file])
            ->assertRedirect(route('manager.team.index'));

        $member = TeamMember::query()->where('email', 'csvuser@example.test')->first();

        $this->assertNotNull($member);
        $this->assertTrue(TeamPermissions::isEnabled($member->permissions, 'inbox_read'));
        $this->assertDatabaseHas('manager_member_assignments', [
            'manager_id' => $manager->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_manager_can_login_as_assigned_member(): void
    {
        $manager = TeamMember::factory()->manager()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        ManagerMemberAssignment::query()->create([
            'parent_user_id' => $this->testUser->id,
            'manager_id' => $manager->id,
            'member_id' => $member->id,
        ]);

        $this->actingAsManager($manager)
            ->post(route('manager.team.login-as', $member))
            ->assertRedirect(route('inbox.index'));

        $this->assertAuthenticatedAs($member, 'team');
        $this->assertEquals($manager->id, session(config('team.impersonation_session_key')));
    }

    public function test_manager_can_update_auto_assign_setting(): void
    {
        $manager = TeamMember::factory()->manager()->create([
            'parent_user_id' => $this->testUser->id,
            'auto_assign_chats' => false,
        ]);

        $this->actingAsManager($manager)
            ->patch(route('manager.settings.update'), ['auto_assign_chats' => '1'])
            ->assertRedirect(route('manager.settings'));

        $this->assertTrue($manager->fresh()->auto_assign_chats);
    }

    public function test_regular_team_member_cannot_access_manager_portal(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('manager.team.index'))
            ->assertForbidden();
    }
}
