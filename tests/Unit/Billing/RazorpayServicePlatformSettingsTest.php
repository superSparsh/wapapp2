<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Domains\Billing\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RazorpayServicePlatformSettingsTest extends TestCase
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

    public function test_reads_credentials_from_admin_platform_settings_not_env(): void
    {
        config([
            'billing.razorpay.key' => 'env_key_should_be_ignored',
            'billing.razorpay.secret' => 'env_secret_should_be_ignored',
        ]);

        app(PlatformSettingsService::class)->save([
            'payment.razorpay_enabled' => '1',
            'payment.razorpay_key' => 'rzp_admin_key',
            'payment.razorpay_secret' => 'rzp_admin_secret',
        ]);

        $service = app(RazorpayService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('rzp_admin_key', $service->key());
        $this->assertSame('rzp_admin_secret', $service->secret());
        $this->assertNotSame('env_key_should_be_ignored', $service->key());
    }

    public function test_not_configured_when_disabled_in_admin(): void
    {
        app(PlatformSettingsService::class)->save([
            'payment.razorpay_enabled' => '0',
            'payment.razorpay_key' => 'rzp_admin_key',
            'payment.razorpay_secret' => 'rzp_admin_secret',
        ]);

        $this->assertFalse(app(RazorpayService::class)->isConfigured());
    }
}
