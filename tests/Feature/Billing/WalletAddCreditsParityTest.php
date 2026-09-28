<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Billing\Console\Commands\ReconcileZohoWalletCommand;
use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Domains\Billing\Services\WalletService;
use App\Domains\Billing\Services\Zoho\ZohoBooksWalletCreditService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\ActivityLog;
use App\Models\RazorpayOrder;
use App\Models\WalletTransaction;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WalletAddCreditsParityTest extends TestCase
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

    public function test_admin_credit_locks_wallet_and_writes_activity_audit(): void
    {
        tenancy()->initialize($this->testTenant);

        $wallet = app(WalletService::class);
        $wallet->adminCredit(100, 'Seed balance', [
            'admin_id' => 1,
            'admin_name' => 'Seed Admin',
            'admin_email' => 'seed@wapapp.test',
        ]);

        $txn = $wallet->adminCredit(250.5, 'Admin wallet top-up', [
            'admin_id' => 99,
            'admin_name' => 'Ops Admin',
            'admin_email' => 'ops@wapapp.test',
        ]);

        $this->assertSame(WalletTransactionType::Credit, $txn->type);
        $this->assertSame(250.5, (float) $txn->amount);
        $this->assertSame(350.5, (float) $txn->balance_after);
        $this->assertSame(100.0, (float) data_get($txn->metadata, 'previous_balance'));
        $this->assertSame('Ops Admin', data_get($txn->metadata, 'admin_name'));
        $this->assertSame(350.5, $wallet->balance());

        $log = ActivityLog::query()
            ->where('action', 'billing.wallet.admin_credit')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('ops@wapapp.test', $log->actor_email);
        $this->assertStringContainsString('250.50', (string) $log->description);
        $this->assertSame(99, data_get($log->metadata, 'admin_id'));

        tenancy()->end();
    }

    public function test_zoho_job_sends_rich_completion_email_once(): void
    {
        tenancy()->initialize($this->testTenant);

        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_test_1',
            'razorpay_payment_id' => 'pay_test_1',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 1000,
            'tax_amount' => 180,
            'total_amount' => 1180,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Paid,
            'paid_at' => now(),
        ]);

        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 1000,
            'currency' => 'INR',
            'balance_after' => 1500,
            'description' => 'Razorpay payment pay_test_1',
            'reference_type' => RazorpayOrder::class,
            'reference_id' => $order->id,
            'razorpay_payment_id' => 'pay_test_1',
            'created_at' => now(),
        ]);

        $zoho = Mockery::mock(ZohoBooksWalletCreditService::class);
        $zoho->shouldReceive('isConfigured')->andReturn(true);
        $zoho->shouldReceive('createPaidWalletCreditInvoice')->once()->andReturn([
            'invoice_id' => 'inv_123',
            'invoice_number' => 'INV-1001',
            'invoice_url' => 'https://books.zoho.test/invoice/inv_123',
            'contact_id' => 'contact_1',
        ]);
        $this->app->instance(ZohoBooksWalletCreditService::class, $zoho);

        $alerts = Mockery::mock(AlertDispatcher::class);
        $alerts->shouldReceive('walletCreditCompleted')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return ($payload['amount'] ?? null) == 1000.0
                    && ($payload['gst_amount'] ?? null) == 180.0
                    && ($payload['paid_amount'] ?? null) == 1180.0
                    && ($payload['previous_balance'] ?? null) == 500.0
                    && ($payload['balance'] ?? null) == 1500.0
                    && ($payload['invoice_url'] ?? null) === 'https://books.zoho.test/invoice/inv_123'
                    && ($payload['invoice_number'] ?? null) === 'INV-1001';
            }));
        $this->app->instance(AlertDispatcher::class, $alerts);

        // Seed wallet balance used by job for "new balance".
        app(WalletService::class)->adminCredit(1500, 'Match txn balance', [
            'admin_name' => 'Test',
        ]);

        $job = new ProcessWalletRazorpayZohoInvoiceJob(
            (string) $this->testTenant->id,
            (int) $order->id,
            'pay_test_1',
            (int) $this->testUser->id,
        );
        $job->handle(
            app(ZohoBooksWalletCreditService::class),
            app(\App\Domains\Billing\Services\BillingAddressService::class),
            app(WalletService::class),
        );

        $ledger = ZohoWalletCreditRequest::query()
            ->where('tenant_id', $this->testTenant->id)
            ->where('external_id', 'pay_test_1')
            ->firstOrFail();

        $this->assertSame('synced', $ledger->status);
        $this->assertSame('INV-1001', $ledger->invoice_number);
        $this->assertNotNull($ledger->completion_email_sent_at);

        // Second run must not re-email / re-create invoice.
        $job->handle(
            app(ZohoBooksWalletCreditService::class),
            app(\App\Domains\Billing\Services\BillingAddressService::class),
            app(WalletService::class),
        );

        $this->assertSame(1, ZohoWalletCreditRequest::query()
            ->where('tenant_id', $this->testTenant->id)
            ->where('external_id', 'pay_test_1')
            ->count());

        tenancy()->end();
    }

    public function test_reconcile_command_queues_failed_razorpay_zoho_jobs(): void
    {
        Queue::fake();

        $zoho = Mockery::mock(ZohoBooksWalletCreditService::class);
        $zoho->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(ZohoBooksWalletCreditService::class, $zoho);

        tenancy()->initialize($this->testTenant);

        $order = RazorpayOrder::query()->create([
            'razorpay_order_id' => 'order_recon_1',
            'razorpay_payment_id' => 'pay_recon_1',
            'purpose' => RazorpayOrderPurpose::WalletRecharge,
            'amount' => 500,
            'tax_amount' => 90,
            'total_amount' => 590,
            'currency' => 'INR',
            'status' => RazorpayOrderStatus::Paid,
            'paid_at' => now(),
        ]);

        ZohoWalletCreditRequest::query()->create([
            'tenant_id' => $this->testTenant->id,
            'source' => 'razorpay',
            'amount' => 500,
            'currency' => 'INR',
            'status' => 'failed',
            'external_id' => 'pay_recon_1',
            'last_error' => 'Zoho timeout',
            'metadata' => [
                'local_order_id' => $order->id,
                'razorpay_payment_id' => 'pay_recon_1',
            ],
        ]);

        tenancy()->end();

        $this->artisan(ReconcileZohoWalletCommand::class, [
            '--tenants' => [$this->testTenant->id],
            '--limit' => 10,
        ])->assertSuccessful();

        Queue::assertPushed(ProcessWalletRazorpayZohoInvoiceJob::class, function ($job) use ($order) {
            return $job->tenantId === (string) $this->testTenant->id
                && $job->razorpayOrderId === (int) $order->id
                && $job->paymentId === 'pay_recon_1';
        });
    }
}
