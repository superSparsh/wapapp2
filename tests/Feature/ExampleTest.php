<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExampleTest extends TestCase
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

    public function test_unauthenticated_user_redirects_to_login(): void
    {
        // Without authentication
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_tenant_user_redirects_to_dashboard(): void
    {
        $response = $this->actingAsTenantUser()->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_team_member_redirects_to_dashboard(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $response = $this->actingAsTeamMember($member)->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_the_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Login to Dashboard');
    }
}
