<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\RazorpayOrder;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WalletPaymentsHubTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Wallet Hub Admin',
            'email' => 'wallet-hub-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

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

    public function test_journeys_list_and_detail_show_status(): void
    {
        tenancy()->initialize($this->testTenant);
        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_HUB_1',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 500,
            'tax_amount' => 90,
            'total_amount' => 590,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Created,
        ]);
        $uuid = (string) $order->uuid;
        tenancy()->end();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-payments.index', ['tenant' => $this->testTenant->id]))
            ->assertOk()
            ->assertSee('order_HUB_1')
            ->assertSee('Awaiting payment');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-payments.show', [$this->testTenant->id, $uuid]))
            ->assertOk()
            ->assertSee('Order created')
            ->assertSee('Complete from Razorpay');
    }

    public function test_complete_from_razorpay_credits_wallet(): void
    {
        Queue::fake();

        tenancy()->initialize($this->testTenant);
        WalletAccount::query()->create([
            'balance' => 50,
            'currency' => 'INR',
        ]);
        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_HUB_COMPLETE',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 1000,
            'tax_amount' => 180,
            'total_amount' => 1180,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Created,
        ]);
        $uuid = (string) $order->uuid;
        tenancy()->end();

        Http::fake([
            'api.razorpay.com/v1/orders/order_HUB_COMPLETE/payments' => Http::response([
                'entity' => 'collection',
                'count' => 1,
                'items' => [[
                    'id' => 'pay_HUB_1',
                    'status' => 'captured',
                    'amount' => 118000,
                    'order_id' => 'order_HUB_COMPLETE',
                ]],
            ]),
            'api.razorpay.com/v1/payments/pay_HUB_1' => Http::response([
                'id' => 'pay_HUB_1',
                'status' => 'captured',
                'amount' => 118000,
                'order_id' => 'order_HUB_COMPLETE',
            ]),
            'api.razorpay.com/v1/orders/order_HUB_COMPLETE' => Http::response([
                'id' => 'order_HUB_COMPLETE',
                'amount' => 118000,
                'status' => 'paid',
            ]),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.wallet-payments.complete', [$this->testTenant->id, $uuid]))
            ->assertRedirect()
            ->assertSessionHas('status');

        tenancy()->initialize($this->testTenant);
        $order = RazorpayOrder::query()->where('razorpay_order_id', 'order_HUB_COMPLETE')->firstOrFail();
        $this->assertSame(RazorpayOrderStatus::Paid, $order->status);
        $this->assertSame('pay_HUB_1', $order->razorpay_payment_id);
        $this->assertTrue(
            WalletTransaction::query()
                ->where('razorpay_payment_id', 'pay_HUB_1')
                ->where('type', WalletTransactionType::Credit)
                ->exists()
        );
        tenancy()->end();

        Queue::assertPushed(ProcessWalletRazorpayZohoInvoiceJob::class);
    }

    public function test_retry_zoho_queues_job(): void
    {
        Queue::fake();

        tenancy()->initialize($this->testTenant);
        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_HUB_ZOHO',
            'razorpay_payment_id' => 'pay_HUB_ZOHO',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 200,
            'tax_amount' => 36,
            'total_amount' => 236,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Paid,
            'paid_at' => now(),
        ]);
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 200,
            'currency' => 'INR',
            'balance_after' => 200,
            'description' => 'Razorpay payment pay_HUB_ZOHO',
            'reference_type' => RazorpayOrder::class,
            'reference_id' => $order->id,
            'razorpay_payment_id' => 'pay_HUB_ZOHO',
            'created_at' => now(),
        ]);
        $uuid = (string) $order->uuid;
        tenancy()->end();

        ZohoWalletCreditRequest::query()->create([
            'tenant_id' => $this->testTenant->id,
            'source' => 'razorpay',
            'amount' => 200,
            'currency' => 'INR',
            'status' => 'failed',
            'external_id' => 'pay_HUB_ZOHO',
            'last_error' => 'timeout',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.wallet-payments.retry-zoho', [$this->testTenant->id, $uuid]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(ProcessWalletRazorpayZohoInvoiceJob::class, function (ProcessWalletRazorpayZohoInvoiceJob $job): bool {
            return $job->tenantId === (string) $this->testTenant->id
                && $job->paymentId === 'pay_HUB_ZOHO';
        });
    }

    public function test_manual_and_credits_tabs_render(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-payments.index', ['tab' => 'manual']))
            ->assertOk()
            ->assertSee('Manual requests');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-payments.index', ['tab' => 'credits']))
            ->assertOk()
            ->assertSee('Wallet credits');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-payments.index', ['tab' => 'zoho']))
            ->assertOk()
            ->assertSee('Zoho credits');
    }
}
