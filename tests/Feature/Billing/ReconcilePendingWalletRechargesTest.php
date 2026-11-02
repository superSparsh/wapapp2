<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Domains\Billing\Console\Commands\ReconcilePendingWalletRechargesCommand;
use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\RazorpayOrder;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ReconcilePendingWalletRechargesTest extends TestCase
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

        config(['billing.wallet.reconcile_created_after' => now()->subHour()->toDateTimeString()]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_credits_paid_created_orders_after_cutoff(): void
    {
        Queue::fake();

        tenancy()->initialize($this->testTenant);
        WalletAccount::query()->create([
            'balance' => 100,
            'currency' => 'INR',
        ]);
        $newOrder = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_NEW_PAID',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 1000,
            'tax_amount' => 180,
            'total_amount' => 1180,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Created,
        ]);
        $newOrder->forceFill(['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)])->save(['timestamps' => false]);
        tenancy()->end();

        Http::fake([
            'api.razorpay.com/v1/orders/order_NEW_PAID/payments' => Http::response([
                'entity' => 'collection',
                'count' => 1,
                'items' => [[
                    'id' => 'pay_NEW_1',
                    'status' => 'captured',
                    'amount' => 118000,
                    'order_id' => 'order_NEW_PAID',
                ]],
            ]),
            'api.razorpay.com/v1/payments/pay_NEW_1' => Http::response([
                'id' => 'pay_NEW_1',
                'status' => 'captured',
                'amount' => 118000,
                'order_id' => 'order_NEW_PAID',
            ]),
            'api.razorpay.com/v1/orders/order_NEW_PAID' => Http::response([
                'id' => 'order_NEW_PAID',
                'amount' => 118000,
                'status' => 'paid',
            ]),
        ]);

        $this->artisan(ReconcilePendingWalletRechargesCommand::class, [
            '--tenants' => [$this->testTenant->id],
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);
        $order = RazorpayOrder::query()->where('razorpay_order_id', 'order_NEW_PAID')->first();
        $this->assertNotNull($order);
        $this->assertSame(RazorpayOrderStatus::Paid, $order->status);
        $this->assertSame('pay_NEW_1', $order->razorpay_payment_id);
        $this->assertTrue(
            WalletTransaction::query()
                ->where('type', WalletTransactionType::Credit)
                ->where('razorpay_payment_id', 'pay_NEW_1')
                ->where('amount', 1000)
                ->exists(),
        );
        Queue::assertPushed(ProcessWalletRazorpayZohoInvoiceJob::class);
        tenancy()->end();
    }

    public function test_skips_orders_created_before_cutoff(): void
    {
        Queue::fake();

        tenancy()->initialize($this->testTenant);
        WalletAccount::query()->create([
            'balance' => 50,
            'currency' => 'INR',
        ]);
        $oldOrder = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_OLD_STUCK',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 5000,
            'tax_amount' => 900,
            'total_amount' => 5900,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Created,
        ]);
        $oldOrder->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save(['timestamps' => false]);
        tenancy()->end();

        Http::fake();

        $this->artisan(ReconcilePendingWalletRechargesCommand::class, [
            '--tenants' => [$this->testTenant->id],
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);
        $this->assertSame(
            RazorpayOrderStatus::Created,
            RazorpayOrder::query()->where('razorpay_order_id', 'order_OLD_STUCK')->first()?->status,
        );
        $this->assertSame(0, WalletTransaction::query()->count());
        Http::assertNothingSent();
        Queue::assertNothingPushed();
        tenancy()->end();
    }
}
