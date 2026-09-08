<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-tenant Razorpay credentials + template config (one row per tenant)
        Schema::create('commerce_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('client_name', 191);
            $table->string('razorpay_key', 191);
            $table->text('razorpay_secret'); // encrypted at rest
            $table->unsignedBigInteger('payment_template_id')->nullable()->index();
            $table->unsignedBigInteger('confirmation_template_id')->nullable()->index();
            $table->auditable();
        });

        // WhatsApp orders received via commerce messages
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('catalog_id', 64)->nullable()->index();
            $table->string('customer_name', 191)->nullable();
            $table->phoneNumber('customer_phone', nullable: true);
            $table->json('product_items')->nullable();
            $table->decimal('total_price', 14, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->statusColumn('order_status', 'new');  // new/confirmed/shipped/delivered/cancelled
            $table->statusColumn('payment_status', 'pending'); // pending/paid/failed
            $table->unsignedBigInteger('whatsapp_line_id')->nullable()->index();
            $table->string('external_message_id', 64)->nullable()->index();
            $table->string('payment_link', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->auditable();
        });

        // Payment link records (one per Razorpay payment link created)
        Schema::create('commerce_payments', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('internal_order_ref', 32)->unique()->index(); // e.g. WP-1234567890-123
            $table->string('customer_name', 191)->nullable();
            $table->phoneNumber('customer_phone', nullable: true);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('razorpay_payment_link_id', 64)->nullable()->index();
            $table->string('payment_link', 512)->nullable();
            $table->statusColumn('status', 'created'); // created/sent/paid/failed/expired/cancelled
            $table->string('razorpay_payment_id', 64)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_payments');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('commerce_payment_configs');
    }
};
