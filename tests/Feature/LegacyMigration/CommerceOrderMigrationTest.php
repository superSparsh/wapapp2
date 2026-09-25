<?php

declare(strict_types=1);

namespace Tests\Feature\LegacyMigration;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Domains\Commerce\Models\CommerceOrder;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Importers\CommerceImporter;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CommerceOrderMigrationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('legacy-migration.connection', 'legacy');

        // Fresh in-memory connection per test (shared PDO would otherwise keep tables).
        DB::purge('legacy');
        DB::reconnect('legacy');

        $schema = Schema::connection('legacy');
        $schema->dropAllTables();
        $schema->create('business_infos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->text('waba_id')->nullable();
            $table->timestamps();
        });
        $schema->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('catalog_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('product_items')->nullable();
            $table->decimal('total_price', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('order_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('message_id')->nullable();
            $table->string('payment_link')->nullable();
            $table->timestamps();
        });
        $schema->create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_link')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_imports_legacy_orders_scoped_by_customer_waba(): void
    {
        $this->testLine->update(['waba_id' => 'WABA-CUSTOMER']);

        DB::connection('legacy')->table('business_infos')->insert([
            'customer_id' => 42,
            'waba_id' => 'WABA-CUSTOMER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('orders')->insert([
            [
                'id' => 1,
                'catalog_id' => 'CAT1',
                'customer_name' => 'Buyer One',
                'customer_phone' => '919876543210',
                'product_items' => json_encode([
                    ['product_retailer_id' => 'SKU1', 'quantity' => 2, 'item_price' => 100, 'currency' => 'INR'],
                ]),
                'total_price' => 200,
                'currency' => 'INR',
                'order_status' => 'New',
                'payment_status' => 'Paid',
                'waba_id' => 'WABA-CUSTOMER',
                'message_id' => 'msg-order-1',
                'payment_link' => 'https://rzp.io/test',
                'created_at' => '2026-01-08 10:00:00',
                'updated_at' => '2026-01-08 10:00:00',
            ],
            [
                'id' => 2,
                'catalog_id' => 'CAT2',
                'customer_name' => 'Other Tenant Buyer',
                'customer_phone' => '911111111111',
                'product_items' => json_encode([]),
                'total_price' => 50,
                'currency' => 'INR',
                'order_status' => 'New',
                'payment_status' => 'Pending',
                'waba_id' => 'WABA-OTHER',
                'message_id' => 'msg-order-2',
                'payment_link' => null,
                'created_at' => '2026-01-08 11:00:00',
                'updated_at' => '2026-01-08 11:00:00',
            ],
        ]);

        $importer = app(CommerceImporter::class);
        $report = new MigrationReport;
        $ids = new MigrationIdMap;
        $customer = new LegacyCustomerSnapshot(
            id: 42,
            uid: 'uid-42',
            email: 'owner@example.com',
            companyName: 'Acme',
            firstName: 'Owner',
            lastName: 'One',
            phone: '919999999999',
            passwordHash: null,
            walletAmount: 0,
            counts: [],
        );

        $importer->import($customer, $this->testTenant, $ids, $report, dryRun: false);

        $this->assertSame(1, CommerceOrder::query()->count());
        $order = CommerceOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('Buyer One', $order->customer_name);
        $this->assertSame('919876543210', $order->customer_phone);
        $this->assertSame('msg-order-1', $order->external_message_id);
        $this->assertSame(OrderStatus::New, $order->order_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame((int) $this->testLine->id, (int) $order->whatsapp_line_id);
        $this->assertSame('2026-01-08 10:00:00', $order->created_at?->format('Y-m-d H:i:s'));
        $this->assertSame($order->id, $ids->getInt('commerce_order', 1));
    }

    public function test_import_orders_is_idempotent_on_message_id(): void
    {
        $this->testLine->update(['waba_id' => 'WABA-CUSTOMER']);

        DB::connection('legacy')->table('business_infos')->insert([
            'customer_id' => 42,
            'waba_id' => 'WABA-CUSTOMER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('legacy')->table('orders')->insert([
            'id' => 9,
            'catalog_id' => 'CAT1',
            'customer_name' => 'Buyer',
            'customer_phone' => '919876543210',
            'product_items' => json_encode([['product_retailer_id' => 'SKU1', 'quantity' => 1, 'item_price' => 10]]),
            'total_price' => 10,
            'currency' => 'INR',
            'order_status' => 'New',
            'payment_status' => 'Pending',
            'waba_id' => 'WABA-CUSTOMER',
            'message_id' => 'msg-dup',
            'payment_link' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importer = app(CommerceImporter::class);
        $customer = new LegacyCustomerSnapshot(
            id: 42,
            uid: 'uid-42',
            email: 'owner@example.com',
            companyName: 'Acme',
            firstName: 'Owner',
            lastName: 'One',
            phone: '919999999999',
            passwordHash: null,
            walletAmount: 0,
            counts: [],
        );

        $importer->import($customer, $this->testTenant, new MigrationIdMap, new MigrationReport, dryRun: false);
        $importer->import($customer, $this->testTenant, new MigrationIdMap, new MigrationReport, dryRun: false);

        $this->assertSame(1, CommerceOrder::query()->count());
    }

    public function test_imports_legacy_payments_and_updates_stats(): void
    {
        $this->testLine->update(['waba_id' => 'WABA-CUSTOMER']);

        DB::connection('legacy')->table('business_infos')->insert([
            'customer_id' => 42,
            'waba_id' => 'WABA-CUSTOMER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('legacy')->table('orders')->insert([
            'id' => 11,
            'catalog_id' => 'CAT1',
            'customer_name' => 'Buyer One',
            'customer_phone' => '919876543210',
            'product_items' => json_encode([['product_retailer_id' => 'SKU1', 'quantity' => 1, 'item_price' => 500]]),
            'total_price' => 500,
            'currency' => 'INR',
            'order_status' => 'New',
            'payment_status' => 'Pending',
            'waba_id' => 'WABA-CUSTOMER',
            'message_id' => 'msg-pay-1',
            'payment_link' => 'https://rzp.io/test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('legacy')->table('payments')->insert([
            [
                'id' => 101,
                'order_id' => 11,
                'customer_id' => 42,
                'customer_name' => 'Buyer One',
                'customer_phone' => '919876543210',
                'amount' => 500,
                'currency' => 'INR',
                'payment_link' => 'https://rzp.io/test',
                'payment_status' => 'Paid',
                'razorpay_payment_id' => 'pay_ABC123',
                'created_at' => '2026-01-08 10:00:00',
                'updated_at' => '2026-01-08 10:05:00',
            ],
            [
                'id' => 102,
                'order_id' => null,
                'customer_id' => 42,
                'customer_name' => 'Pending Buyer',
                'customer_phone' => '911111111111',
                'amount' => 250,
                'currency' => 'INR',
                'payment_link' => 'https://rzp.io/pending',
                'payment_status' => 'Pending',
                'razorpay_payment_id' => null,
                'created_at' => '2026-01-08 11:00:00',
                'updated_at' => '2026-01-08 11:00:00',
            ],
            [
                'id' => 103,
                'order_id' => null,
                'customer_id' => 99,
                'customer_name' => 'Other Tenant',
                'customer_phone' => '912222222222',
                'amount' => 999,
                'currency' => 'INR',
                'payment_link' => null,
                'payment_status' => 'Paid',
                'razorpay_payment_id' => 'pay_OTHER',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $importer = app(CommerceImporter::class);
        $customer = new LegacyCustomerSnapshot(
            id: 42,
            uid: 'uid-42',
            email: 'owner@example.com',
            companyName: 'Acme',
            firstName: 'Owner',
            lastName: 'One',
            phone: '919999999999',
            passwordHash: null,
            walletAmount: 0,
            counts: [],
        );

        $importer->import($customer, $this->testTenant, new MigrationIdMap, new MigrationReport, dryRun: false);

        $this->assertSame(2, \App\Domains\Commerce\Models\CommercePayment::query()->count());
        $paid = \App\Domains\Commerce\Models\CommercePayment::query()->where('status', \App\Domains\Commerce\Enums\PaymentLinkStatus::Paid)->first();
        $this->assertNotNull($paid);
        $this->assertSame('Buyer One', $paid->customer_name);
        $this->assertSame('pay_ABC123', $paid->razorpay_payment_id);
        $this->assertSame(PaymentStatus::Paid, CommerceOrder::query()->first()?->payment_status);

        $stats = app(\App\Domains\Commerce\Services\CommercePaymentService::class)->getStats();
        $this->assertSame(500.0, $stats['total_paid']);
        $this->assertSame(250.0, $stats['total_pending']);
        $this->assertSame(1, $stats['paid_count']);
        $this->assertSame(1, $stats['pending_count']);
    }
}
