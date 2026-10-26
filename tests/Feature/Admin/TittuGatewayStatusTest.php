<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TittuGatewayStatusTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Gateway Admin',
            'email' => 'gateway-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        config([
            'services.tittu_gateway.url' => 'https://gateway.tittu.in',
            'services.tittu_gateway.status_token' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.tittu-gateway.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_gateway_down_without_calling_provider_status(): void
    {
        Http::fake([
            'https://gateway.tittu.in/health' => Http::response('nope', 503),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tittu-gateway.index'))
            ->assertOk()
            ->assertSee('Down')
            ->assertSee('Add a gateway status token');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/admin/status'));
    }

    public function test_admin_sees_provider_activity_and_hides_secrets(): void
    {
        config(['services.tittu_gateway.status_token' => 'status-token-secret']);

        Http::fake([
            'https://gateway.tittu.in/health' => Http::response(['status' => 'ok'], 200),
            'https://gateway.tittu.in/admin/status' => Http::response([
                'status' => 'ok',
                'activeClientKeysCount' => 2,
                'providers' => [
                    [
                        'id' => 'gemini',
                        'priority' => 1,
                        'adapter' => 'gemini',
                        'enabled' => true,
                        'hasKey' => true,
                        'isCoolingDown' => false,
                        'requestsToday' => 4,
                        'requestsThisMinute' => 1,
                        'limitRpm' => 15,
                        'limitRpd' => 1500,
                        'models' => ['gemini-2.5-flash'],
                        'apiKey' => 'secret-should-not-leak',
                    ],
                    [
                        'id' => 'groq',
                        'priority' => 2,
                        'adapter' => 'groq',
                        'enabled' => true,
                        'hasKey' => true,
                        'isCoolingDown' => true,
                        'cooldownRemainingSeconds' => 40,
                        'cooldownReason' => 'rate_limit',
                        'requestsToday' => 9,
                        'requestsThisMinute' => 8,
                        'limitRpm' => 10,
                        'limitRpd' => 1000,
                        'models' => [],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tittu-gateway.index'))
            ->assertOk()
            ->assertSee('Up')
            ->assertSee('1 / 2 providers active')
            ->assertSee('gemini')
            ->assertSee('Active')
            ->assertSee('Cooling')
            ->assertSee('rate_limit')
            ->assertDontSee('secret-should-not-leak')
            ->assertDontSee('status-token-secret');
    }

    public function test_admin_can_save_a_status_token_without_echoing_it(): void
    {
        Http::fake([
            'https://gateway.tittu.in/health' => Http::response(['status' => 'ok'], 200),
            'https://gateway.tittu.in/admin/status' => Http::response(['status' => 'ok', 'providers' => []], 200),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.tittu-gateway.update'), [
                'status_token' => 'saved-gateway-token',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(
            'saved-gateway-token',
            PlatformSetting::query()->where('key', 'tittu_gateway.status_token')->value('value'),
        );

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tittu-gateway.index'))
            ->assertOk()
            ->assertDontSee('saved-gateway-token')
            ->assertSee('A token is saved in admin.');
    }

    public function test_customers_tab_lists_tittu_usage_only(): void
    {
        \App\Models\AiProviderKey::factory()->create([
            'provider' => 'tittu',
            'is_active' => true,
            'is_validated' => true,
        ]);

        $bot = \App\Models\AiBot::factory()->create([
            'name' => 'Estate Assistant',
            'provider' => 'tittu',
            'chat_model' => 'auto',
            'embedding_model' => '',
            'status' => 'active',
        ]);

        \App\Models\AiTokenUsageLog::query()->create([
            'ai_bot_id' => $bot->id,
            'provider' => 'tittu',
            'model' => 'auto',
            'request_type' => 'chat',
            'prompt_tokens' => 10,
            'completion_tokens' => 4,
            'total_tokens' => 14,
        ]);

        \App\Models\AiTokenUsageLog::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'request_type' => 'chat',
            'prompt_tokens' => 1,
            'completion_tokens' => 1,
            'total_tokens' => 99999,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tittu-gateway.index', ['tab' => 'customers']))
            ->assertOk()
            ->assertSee('Tittu Gateway (AI)')
            ->assertSee('Test Tenant')
            ->assertSee('Estate Assistant')
            ->assertSee('Active')
            ->assertDontSee('99999');
    }
}
