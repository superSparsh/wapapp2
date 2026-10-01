<?php

declare(strict_types=1);

namespace Tests\Feature\MobileApi;

use App\Domains\MobileApi\Services\JwtTokenService;
use App\Enums\TenantUserAccountType;
use App\Models\TenantUserAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MobileAuthTest extends TestCase
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

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_login_returns_jwt_pair(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'email', 'first_name', 'last_name'],
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertSame('Bearer', $response->json('data.token_type'));
        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertArrayHasKey('wallet_amount', $response->json('data.user'));
        $this->assertArrayHasKey('valid_until', $response->json('data.user'));
        $this->assertArrayHasKey('remaining_days', $response->json('data.user'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_me_requires_access_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_me_returns_profile_with_jwt(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $this->testUser->email);
    }

    public function test_refresh_issues_new_tokens(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ])->assertOk();

        $refresh = $login->json('data.refresh_token');

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refresh,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'user'],
            ]);
    }

    public function test_refresh_rejects_access_token(): void
    {
        tenancy()->initialize($this->testTenant);
        $jwt = app(JwtTokenService::class);
        $access = $jwt->issuePair($this->testUser, $this->testTenant->id, 'web')['access_token'];
        tenancy()->end();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $access,
        ])->assertStatus(401);
    }

    public function test_logout_succeeds(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
