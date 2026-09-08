<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentLinkStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Domains\Commerce\Models\CommerceOrder;
use App\Domains\Commerce\Models\CommercePayment;
use App\Domains\Commerce\Models\PaymentConfig;
use App\Domains\Commerce\Services\CatalogService;
use App\Domains\Commerce\Services\CommerceOrderService;
use App\Domains\Commerce\Services\CommercePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CommerceTest extends TestCase
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

    // ─── Page accessibility ───────────────────────────────────────────────────

    public function test_owner_can_view_commerce_index(): void
    {
        $this->actingAsTenantUser()
            ->get(route('commerce.index'))
            ->assertOk()
            ->assertViewIs('commerce.index');
    }

    public function test_owner_can_view_orders_page(): void
    {
        $this->actingAsTenantUser()
            ->get(route('commerce.orders'))
            ->assertOk()
            ->assertViewIs('commerce.orders');
    }

    public function test_owner_can_view_settings_page(): void
    {
        $this->actingAsTenantUser()
            ->get(route('commerce.settings'))
            ->assertOk()
            ->assertViewIs('commerce.settings');
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('commerce.settings'))
            ->assertRedirect();
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    public function test_orders_page_shows_empty_state(): void
    {
        $this->actingAsTenantUser()
            ->get(route('commerce.orders'))
            ->assertOk()
            ->assertSee('No orders found');
    }

    public function test_orders_page_shows_existing_orders(): void
    {
        $order = CommerceOrder::factory()->create(['customer_name' => 'Sohel Shaikh']);

        $this->actingAsTenantUser()
            ->get(route('commerce.orders'))
            ->assertOk()
            ->assertSee('Sohel Shaikh');
    }

    public function test_orders_can_be_filtered_by_order_status(): void
    {
        CommerceOrder::factory()->create(['order_status' => OrderStatus::New, 'customer_name' => 'New Customer']);
        CommerceOrder::factory()->create(['order_status' => OrderStatus::Delivered, 'customer_name' => 'Delivered Customer']);

        $response = $this->actingAsTenantUser()
            ->get(route('commerce.orders', ['order_status' => 'new']));

        $response->assertOk()
            ->assertSee('New Customer')
            ->assertDontSee('Delivered Customer');
    }

    public function test_orders_can_be_filtered_by_payment_status(): void
    {
        CommerceOrder::factory()->paid()->create(['customer_name' => 'Paid Customer']);
        CommerceOrder::factory()->create(['customer_name' => 'Pending Customer', 'payment_status' => PaymentStatus::Pending]);

        $response = $this->actingAsTenantUser()
            ->get(route('commerce.orders', ['payment_status' => 'paid']));

        $response->assertOk()
            ->assertSee('Paid Customer')
            ->assertDontSee('Pending Customer');
    }

    public function test_orders_can_be_searched(): void
    {
        CommerceOrder::factory()->create(['customer_name' => 'Sparsh Thakur']);
        CommerceOrder::factory()->create(['customer_name' => 'John Doe']);

        $response = $this->actingAsTenantUser()
            ->get(route('commerce.orders', ['q' => 'Sparsh']));

        $response->assertOk()
            ->assertSee('Sparsh Thakur')
            ->assertDontSee('John Doe');
    }

    public function test_order_detail_returns_json(): void
    {
        $order = CommerceOrder::factory()->create([
            'customer_name'  => 'Test User',
            'customer_phone' => '919876543210',
            'total_price'    => 5900.00,
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('commerce.orders.detail', $order->uuid))
            ->assertOk()
            ->assertJsonPath('customer_name', 'Test User')
            ->assertJsonPath('customer_phone', '919876543210')
            ->assertJsonStructure([
                'id', 'uuid', 'customer_name', 'customer_phone',
                'product_items', 'total_price', 'currency',
                'order_status', 'payment_status', 'created_at',
            ]);
    }

    public function test_order_detail_returns_404_for_invalid_uuid(): void
    {
        $this->actingAsTenantUser()
            ->getJson(route('commerce.orders.detail', 'nonexistent-uuid'))
            ->assertNotFound();
    }

    // ─── Payment Configuration ────────────────────────────────────────────────

    public function test_settings_page_shows_config_not_set_message_when_empty(): void
    {
        $this->actingAsTenantUser()
            ->get(route('commerce.settings'))
            ->assertOk()
            ->assertSee('No payment configuration yet');
    }

    public function test_settings_page_shows_existing_config(): void
    {
        PaymentConfig::factory()->create([
            'client_name'  => 'Tekpro Solutions',
            'razorpay_key' => 'rzp_test_abc123',
        ]);

        $this->actingAsTenantUser()
            ->get(route('commerce.settings'))
            ->assertOk()
            ->assertSee('Tekpro Solutions');
    }

    public function test_owner_can_save_payment_config(): void
    {
        $this->actingAsTenantUser()
            ->post(route('commerce.settings.save'), [
                'client_name'     => 'My Business',
                'razorpay_key'    => 'rzp_test_testkey123',
                'razorpay_secret' => 'super_secret_value',
            ])
            ->assertRedirect(route('commerce.settings'))
            ->assertSessionHas('success');

        $config = PaymentConfig::query()->first();
        $this->assertNotNull($config);
        $this->assertSame('My Business', $config->client_name);
        $this->assertSame('rzp_test_testkey123', $config->razorpay_key);
        // Secret is encrypted
        $this->assertSame('super_secret_value', $config->razorpay_secret);
    }

    public function test_save_config_updates_existing_record(): void
    {
        $config = PaymentConfig::factory()->create(['client_name' => 'Old Name']);

        $this->actingAsTenantUser()
            ->post(route('commerce.settings.save'), [
                'client_name'  => 'New Name',
                'razorpay_key' => $config->razorpay_key,
                // secret omitted on update (should preserve existing)
            ])
            ->assertRedirect(route('commerce.settings'));

        $this->assertSame('New Name', $config->refresh()->client_name);
        $this->assertSame(1, PaymentConfig::query()->count());
    }

    public function test_save_config_requires_client_name(): void
    {
        $this->actingAsTenantUser()
            ->post(route('commerce.settings.save'), [
                'razorpay_key'    => 'rzp_test_key',
                'razorpay_secret' => 'secret',
            ])
            ->assertSessionHasErrors('client_name');
    }

    public function test_save_config_requires_razorpay_key(): void
    {
        $this->actingAsTenantUser()
            ->post(route('commerce.settings.save'), [
                'client_name'     => 'Test',
                'razorpay_secret' => 'secret',
            ])
            ->assertSessionHasErrors('razorpay_key');
    }

    // ─── Payment Creation ─────────────────────────────────────────────────────

    public function test_create_payment_requires_config(): void
    {
        // No config exists
        $this->actingAsTenantUser()
            ->post(route('commerce.payments.create'), [
                'customer_name'  => 'John Doe',
                'customer_phone' => '9876543210',
                'amount'         => 500,
            ])
            ->assertRedirect(route('commerce.settings'))
            ->assertSessionHasErrors('payment');
    }

    public function test_create_payment_validates_required_fields(): void
    {
        PaymentConfig::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('commerce.payments.create'), [])
            ->assertSessionHasErrors(['customer_name', 'customer_phone', 'amount']);
    }

    public function test_create_payment_validates_amount_minimum(): void
    {
        PaymentConfig::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('commerce.payments.create'), [
                'customer_name'  => 'John',
                'customer_phone' => '9876543210',
                'amount'         => 0,
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_create_payment_creates_record_and_dispatches_job(): void
    {
        Queue::fake();

        PaymentConfig::factory()->create([
            'razorpay_key'    => 'rzp_test_key',
            'razorpay_secret' => 'test_secret',
        ]);

        // Fake Razorpay API response
        Http::fake([
            'api.razorpay.com/*' => Http::response([
                'id'        => 'plink_test123',
                'short_url' => 'https://rzp.io/l/testlink',
                'amount'    => 50000,
                'status'    => 'created',
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->post(route('commerce.payments.create'), [
                'customer_name'  => 'Sohel Shaikh',
                'customer_phone' => '9876543210',
                'amount'         => '500',
                'currency'       => 'INR',
            ])
            ->assertRedirect(route('commerce.settings'))
            ->assertSessionHas('success');

        $payment = CommercePayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame('Sohel Shaikh', $payment->customer_name);
        $this->assertSame(PaymentLinkStatus::Created, $payment->status);
        $this->assertSame('https://rzp.io/l/testlink', $payment->payment_link);

        Queue::assertPushed(\App\Domains\Commerce\Jobs\SendPaymentLinkJob::class);
    }

    // ─── Payment Stats ────────────────────────────────────────────────────────

    public function test_payment_stats_returns_correct_totals(): void
    {
        CommercePayment::factory()->paid()->create(['amount' => 1000]);
        CommercePayment::factory()->paid()->create(['amount' => 2000]);
        CommercePayment::factory()->create(['amount' => 500, 'status' => PaymentLinkStatus::Sent]);

        $service = app(CommercePaymentService::class);
        $stats   = $service->getStats();

        $this->assertSame(3000.0, $stats['total_paid']);
        $this->assertSame(500.0, $stats['total_pending']);
        $this->assertSame(2, $stats['paid_count']);
        $this->assertSame(1, $stats['pending_count']);
    }

    // ─── Order Stats ──────────────────────────────────────────────────────────

    public function test_order_stats_returns_correct_counts(): void
    {
        CommerceOrder::factory()->count(3)->create(['order_status' => OrderStatus::New]);
        CommerceOrder::factory()->count(2)->delivered()->create();
        CommerceOrder::factory()->count(1)->cancelled()->create();

        $service = app(CommerceOrderService::class);
        $stats   = $service->getStats();

        $this->assertSame(6, $stats['total']);
        $this->assertSame(3, $stats['new']);
        $this->assertSame(2, $stats['delivered']);
        $this->assertSame(1, $stats['cancelled']);
    }

    // ─── Payment Webhook Callback ─────────────────────────────────────────────

    public function test_payment_callback_marks_payment_as_paid(): void
    {
        $payment = CommercePayment::factory()->sent()->create([
            'razorpay_payment_link_id' => 'plink_test999',
        ]);

        $this->actingAsTenantUser()
            ->get(route('commerce.payment.callback', [
                'razorpay_payment_link_id'     => 'plink_test999',
                'razorpay_payment_id'           => 'pay_test_abc',
                'razorpay_payment_link_status'  => 'paid',
            ]))
            ->assertOk()
            ->assertViewIs('commerce.payment-callback');

        $payment->refresh();
        $this->assertSame(PaymentLinkStatus::Paid, $payment->status);
        $this->assertSame('pay_test_abc', $payment->razorpay_payment_id);
        $this->assertNotNull($payment->paid_at);
    }

    // ─── CommerceOrderService unit tests ─────────────────────────────────────

    public function test_order_service_enriches_items_with_product_data(): void
    {
        $order = CommerceOrder::factory()->create([
            'product_items' => [
                ['product_retailer_id' => 'RET001', 'quantity' => 1],
            ],
        ]);

        $products = [
            ['retailer_id' => 'RET001', 'name' => 'Widget Pro', 'image_url' => 'https://img.test/1.jpg', 'price' => '₹ 999'],
        ];

        $service  = app(CommerceOrderService::class);
        $enriched = $service->enrichOrderItems($order, $products);

        $this->assertSame('Widget Pro', $enriched->product_items[0]['name']);
        $this->assertSame('https://img.test/1.jpg', $enriched->product_items[0]['image_url']);
    }

    // ─── CatalogService unit tests ────────────────────────────────────────────

    public function test_catalog_service_returns_error_when_cams_not_configured(): void
    {
        // Clear CAMS config
        config(['whatsapp.alibaba.access_key_id' => null]);

        $service = app(CatalogService::class);
        $result  = $service->getCatalogs($this->testLine);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['message']);
    }

    public function test_catalog_service_returns_error_when_no_cust_space_id(): void
    {
        config([
            'whatsapp.alibaba.access_key_id'     => 'test_key',
            'whatsapp.alibaba.access_key_secret'  => 'test_secret',
        ]);

        // testLine has no alibaba_cust_space_id by default
        $service = app(CatalogService::class);
        $result  = $service->getCatalogs($this->testLine);

        $this->assertFalse($result['success']);
    }

    public function test_catalog_service_fetches_and_formats_catalogs(): void
    {
        config([
            'whatsapp.alibaba.access_key_id'     => 'test_key',
            'whatsapp.alibaba.access_key_secret'  => 'test_secret',
        ]);

        $this->testLine->update(['alibaba_cust_space_id' => 'SP123456']);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'model' => [
                    'data' => [
                        [
                            'id'            => 'CAT001',
                            'name'          => 'Our Services',
                            'product_count' => 5,
                            'vertical'      => 'commerce',
                            'default_image_url' => '',
                            'business'      => ['id' => 'B001', 'name' => 'Test Business'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(CatalogService::class);
        $result  = $service->getCatalogs($this->testLine);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['catalogs']);
        $this->assertSame('CAT001', $result['catalogs'][0]['id']);
        $this->assertSame('Our Services', $result['catalogs'][0]['name']);
        $this->assertSame(5, $result['catalogs'][0]['product_count']);
    }

    public function test_catalog_service_fetches_and_formats_products(): void
    {
        config([
            'whatsapp.alibaba.access_key_id'     => 'test_key',
            'whatsapp.alibaba.access_key_secret'  => 'test_secret',
        ]);

        $this->testLine->update([
            'alibaba_cust_space_id' => 'SP123456',
            'waba_id'               => 'WABA001',
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'model' => [
                    'data' => [
                        [
                            'id'           => 'P001',
                            'retailer_id'  => 'RET001',
                            'name'         => 'Widget Pro',
                            'description'  => 'Great widget',
                            'brand'        => 'Acme',
                            'price'        => '999',
                            'condition'    => 'new',
                            'availability' => 'in stock',
                            'image_url'    => 'https://img.test/widget.jpg',
                            'inventory'    => 50,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(CatalogService::class);
        $result  = $service->getProducts($this->testLine, 'CAT001');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['products']);
        $this->assertSame('Widget Pro', $result['products'][0]['name']);
        $this->assertSame('₹ 999', $result['products'][0]['price']);
        $this->assertSame('RET001', $result['products'][0]['retailer_id']);
    }

    // ─── Payment model helpers ────────────────────────────────────────────────

    public function test_payment_is_paid_returns_true_for_paid_status(): void
    {
        $payment = CommercePayment::factory()->paid()->create();
        $this->assertTrue($payment->isPaid());
    }

    public function test_payment_is_expired_for_past_expiry(): void
    {
        $payment = CommercePayment::factory()->expired()->create();
        $this->assertTrue($payment->isExpired());
    }

    public function test_order_is_paid_helper(): void
    {
        $paid    = CommerceOrder::factory()->paid()->create();
        $pending = CommerceOrder::factory()->create();

        $this->assertTrue($paid->isPaid());
        $this->assertFalse($pending->isPaid());
    }
}
