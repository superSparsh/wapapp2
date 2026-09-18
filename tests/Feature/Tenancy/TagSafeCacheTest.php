<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domains\Admin\Services\MaintenanceModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TagSafeCacheTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        config(['cache.default' => 'file']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_cache_remember_works_on_file_store_after_tenancy_boots(): void
    {
        tenancy()->initialize($this->testTenant);

        $value = Cache::remember('tag-safe-probe', 60, fn () => 'ok');

        $this->assertSame('ok', $value);
        $this->assertSame('ok', Cache::get('tag-safe-probe'));
    }

    public function test_dashboard_loads_when_cache_store_does_not_support_tags(): void
    {
        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_maintenance_state_is_visible_inside_tenant_context(): void
    {
        app(MaintenanceModeService::class)->save([
            'enabled' => true,
            'message' => 'File-cache maintenance window',
            'modules' => [],
        ]);

        tenancy()->initialize($this->testTenant);

        $this->assertTrue(app(MaintenanceModeService::class)->enabled());
        $this->assertSame('File-cache maintenance window', app(MaintenanceModeService::class)->message());
    }
}
