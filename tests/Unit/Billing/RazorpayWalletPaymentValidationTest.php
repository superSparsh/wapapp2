<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Domains\Billing\Services\RazorpayService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Models\RazorpayOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RazorpayWalletPaymentValidationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        app(PlatformSettingsService::class)->save([
            'payment.razorpay_enabled' => '1',
            'payment.razorpay_key' => 'rzp_test_key',
            'payment.razorpay_secret' => 'rzp_test_secret',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_allows_overpayment_fees_and_rejects_wrong_order(): void
    {
        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_ABC',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 1000,
            'tax_amount' => 180,
            'total_amount' => 1180,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Created,
        ]);

        Http::fake([
            'api.razorpay.com/v1/payments/pay_1' => Http::response([
                'id' => 'pay_1',
                'status' => 'captured',
                'order_id' => 'order_ABC',
                'amount' => 118050, // +50 paise fees
            ], 200),
            'api.razorpay.com/v1/orders/order_ABC' => Http::response([
                'id' => 'order_ABC',
                'amount' => 118000,
            ], 200),
        ]);

        $result = app(RazorpayService::class)->assertPaymentMatchesOrder($order, 'pay_1');
        $this->assertSame('captured', $result['payment']['status']);

        Http::fake([
            'api.razorpay.com/v1/payments/pay_2' => Http::response([
                'id' => 'pay_2',
                'status' => 'captured',
                'order_id' => 'order_OTHER',
                'amount' => 118000,
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment does not belong to this order.');
        app(RazorpayService::class)->assertPaymentMatchesOrder($order, 'pay_2');
    }

    public function test_rejects_underpayment(): void
    {
        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_UNDER',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 1000,
            'tax_amount' => 180,
            'total_amount' => 1180,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Created,
        ]);

        Http::fake([
            'api.razorpay.com/v1/payments/pay_u' => Http::response([
                'id' => 'pay_u',
                'status' => 'captured',
                'order_id' => 'order_UNDER',
                'amount' => 100000,
            ], 200),
            'api.razorpay.com/v1/orders/order_UNDER' => Http::response([
                'id' => 'order_UNDER',
                'amount' => 118000,
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Paid amount does not match the GST-inclusive order.');
        app(RazorpayService::class)->assertPaymentMatchesOrder($order, 'pay_u');
    }
}
