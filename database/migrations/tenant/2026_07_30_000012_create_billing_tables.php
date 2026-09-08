<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('status', 32)->default('active')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('razorpay_subscription_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->auditable();
        });

        Schema::create('billing_addresses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('gst_treatment', 120)->nullable();
            $table->string('company_name');
            $table->string('pan', 20)->nullable();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 120);
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 2)->default('IN');
            $table->boolean('is_default')->default(true);
            $table->auditable();
        });

        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->decimal('balance', 14, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 16)->index();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->decimal('balance_after', 14, 2);
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('razorpay_payment_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('razorpay_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->index();
            $table->string('purpose', 32)->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('status', 32)->default('created')->index();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_orders');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('billing_addresses');
        Schema::dropIfExists('subscriptions');
    }
};
