<?php

namespace Tests\Feature\Team;

use App\Domains\Team\Mail\TeamMemberInviteMail;
use App\Domains\Team\Support\TeamPermissions;
use App\Enums\TeamMemberRole;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TeamMemberInviteTest extends TestCase
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

    public function test_creating_team_member_sends_invite_email_without_password(): void
    {
        Mail::fake();

        $this->actingAsTenantUser()
            ->post(route('my-team.store'), [
                'first_name' => 'Invite',
                'last_name' => 'User',
                'phone' => '9876543211',
                'email' => 'invitee@example.test',
                'role' => TeamMemberRole::Member->value,
            ])
            ->assertRedirect();

        $member = TeamMember::query()->where('email', 'invitee@example.test')->first();
        $this->assertNotNull($member);

        Mail::assertSent(TeamMemberInviteMail::class, function (TeamMemberInviteMail $mail) use ($member): bool {
            return $mail->hasTo('invitee@example.test')
                && $mail->member->is($member)
                && str_contains($mail->setPasswordUrl, 'reset-password');
        });
    }

    public function test_owner_can_resend_invite_email(): void
    {
        Mail::fake();

        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'email' => 'resend@example.test',
        ]);

        $this->actingAsTenantUser()
            ->post(route('my-team.resend-invite', $member))
            ->assertRedirect();

        Mail::assertSent(TeamMemberInviteMail::class, fn (TeamMemberInviteMail $mail): bool => $mail->hasTo('resend@example.test'));
    }

    public function test_team_member_cannot_see_account_section_in_navigation(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $labels = collect(\App\Domains\Team\Support\TeamNavigation::items($member))
            ->pluck('label')
            ->all();

        $this->assertNotContains('Account', $labels);
        $this->assertNotContains('Security', $labels);
    }

    public function test_team_member_cannot_access_account_security(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('profile.security'))
            ->assertForbidden();
    }

    public function test_roles_page_shows_new_module_permissions(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('my-team.roles', $member))
            ->assertOk()
            ->assertSee('Chat bot')
            ->assertSee('Drip Marketing')
            ->assertSee('WhatsApp Flows')
            ->assertSee('Form builder')
            ->assertSee('Commerce')
            ->assertSee('Connected Apps');
    }

    public function test_team_member_with_chatbot_permission_can_open_chatbot(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), [
                'inbox_read' => true,
                'chatbot_read' => true,
            ]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('chatbot.index'))
            ->assertOk();
    }

    public function test_team_member_without_chatbot_permission_cannot_open_chatbot(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('chatbot.index'))
            ->assertForbidden();
    }

    public function test_deleted_team_member_email_can_be_reused(): void
    {
        Mail::fake();

        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'email' => 'reuse@example.test',
            'phone' => '919876543299',
        ]);

        $this->actingAsTenantUser()
            ->delete(route('my-team.destroy', $member))
            ->assertRedirect(route('my-team.index'));

        $this->assertSoftDeleted('team_members', ['id' => $member->id]);

        $this->actingAsTenantUser()
            ->post(route('my-team.store'), [
                'first_name' => 'Again',
                'last_name' => 'User',
                'phone' => '9876543299',
                'email' => 'reuse@example.test',
                'role' => TeamMemberRole::Member->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('team_members', [
            'email' => 'reuse@example.test',
            'deleted_at' => null,
        ]);
    }
}
