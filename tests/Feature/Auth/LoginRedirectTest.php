<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TenantUserAccountType;
use App\Models\TenantUserAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_unverified_user_login_lands_on_email_verification_without_loop(): void
    {
        $this->testUser->forceFill(['email_verified_at' => null])->save();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $response = $this->followRedirectChain($response, 8);

        $response->assertOk();
        $response->assertSee('Verify your email');
        $this->assertAuthenticatedAs($this->testUser, 'web');
    }

    public function test_unverified_user_can_open_verification_page_while_logged_in(): void
    {
        $this->testUser->forceFill(['email_verified_at' => null])->save();

        $response = $this->actingAsTenantUser()
            ->get(route('signup.email'));

        $response->assertOk();
        $response->assertSee('Verify your email');
    }

    public function test_verified_user_login_reaches_dashboard(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $response = $this->followRedirectChain($response, 8);

        $response->assertOk();
        $this->assertAuthenticatedAs($this->testUser, 'web');
    }

    private function followRedirectChain(\Illuminate\Testing\TestResponse $response, int $maxHops): \Illuminate\Testing\TestResponse
    {
        $hops = 0;

        while ($response->isRedirect()) {
            $hops++;
            $this->assertLessThan(
                $maxHops,
                $hops,
                'Login caused a redirect loop (ERR_TOO_MANY_REDIRECTS).',
            );

            $location = $response->headers->get('Location');
            $this->assertNotEmpty($location);

            $response = $this->get($location);
        }

        return $response;
    }
}
