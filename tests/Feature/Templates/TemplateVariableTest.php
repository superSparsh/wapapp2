<?php

namespace Tests\Feature\Templates;

use App\Domains\Templates\Support\TemplateCatalogCache;
use App\Models\TeamMember;
use App\Models\Variable;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateVariableTest extends TestCase
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
        TemplateCatalogCache::flush();
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_owner_can_view_variables_index(): void
    {
        Variable::factory()->create([
            'name' => 'customer_name',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.variables'))
            ->assertOk()
            ->assertSee('customer_name');
    }

    public function test_owner_can_create_variable(): void
    {
        $this->actingAsTenantUser()
            ->post(route('templates.variables.store'), [
                'name' => 'Order ID',
                'data_type' => 'number',
            ])
            ->assertRedirect(route('templates.variables'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('variables', [
            'name' => 'order_id',
            'data_type' => 'number',
            'whatsapp_line_id' => $this->testLine->id,
            'team_member_id' => null,
        ]);
    }

    public function test_duplicate_variable_name_is_rejected(): void
    {
        Variable::factory()->create([
            'name' => 'order_id',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->from(route('templates.variables.create'))
            ->post(route('templates.variables.store'), [
                'name' => 'order_id',
                'data_type' => 'string',
            ])
            ->assertRedirect(route('templates.variables.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_owner_can_update_variable(): void
    {
        $variable = Variable::factory()->create([
            'name' => 'old_name',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->put(route('templates.variables.update', $variable), [
                'name' => 'new_name',
                'data_type' => 'url',
            ])
            ->assertRedirect(route('templates.variables'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('variables', [
            'id' => $variable->id,
            'name' => 'new_name',
            'data_type' => 'url',
        ]);
    }

    public function test_owner_can_delete_variable(): void
    {
        $variable = Variable::factory()->create([
            'name' => 'remove_me',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->delete(route('templates.variables.destroy', $variable))
            ->assertRedirect(route('templates.variables'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('variables', ['id' => $variable->id]);
    }

    public function test_search_filters_variables(): void
    {
        Variable::factory()->create([
            'name' => 'chatbot_greeting',
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        Variable::factory()->create([
            'name' => 'wallet_balance',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.variables', ['q' => 'chatbot']))
            ->assertOk()
            ->assertSee('chatbot_greeting')
            ->assertDontSee('wallet_balance');
    }

    public function test_team_member_with_permission_can_access_variables(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => array_merge(config('team.default_permissions'), [
                'template_read' => true,
            ]),
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('templates.variables'))
            ->assertOk();
    }

    public function test_team_member_without_permission_is_blocked(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('templates.variables'))
            ->assertForbidden();
    }

    public function test_team_member_only_sees_own_variables(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
            'permissions' => ['template_read' => true],
        ]);

        Variable::factory()->create([
            'name' => 'owner_var',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        Variable::factory()
            ->forTeamMember($member->id, 'Member One')
            ->create([
                'name' => 'member_var',
                'whatsapp_line_id' => $this->testLine->id,
            ]);

        $this->actingAsTeamMember($member)
            ->get(route('templates.variables'))
            ->assertOk()
            ->assertSee('member_var')
            ->assertDontSee('owner_var');
    }

    public function test_chatbot_variables_endpoint_returns_built_ins(): void
    {
        $this->actingAsTenantUser()
            ->getJson(route('templates.variables.chatbot'))
            ->assertOk()
            ->assertJsonPath('status', 1)
            ->assertJsonFragment(['name' => 'first_name'])
            ->assertJsonFragment(['name' => 'current_date']);
    }
}
