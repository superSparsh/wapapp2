<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('country_pricing', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('admin_id')->nullable();
            $table->string('country_name');
            $table->string('country_code')->nullable()->unique();
            $table->string('dial_code')->nullable();
            $table->json('region_codes')->nullable();
            $table->string('currency', 10)->default('₹');
            $table->decimal('marketing_price', 10, 4)->nullable();
            $table->decimal('utility_price', 10, 4)->nullable();
            $table->decimal('auth_price', 10, 4)->nullable();
            $table->decimal('auth_international_price', 10, 4)->nullable();
            $table->decimal('service_price', 10, 4)->nullable();
            $table->double('tekpro_marketing_price', 10, 4)->nullable();
            $table->double('tekpro_utility_price', 10, 4)->nullable();
            $table->double('tekpro_auth_price', 10, 4)->nullable();
            $table->double('tekpro_auth_international_price', 10, 4)->nullable();
            $table->double('tekpro_service_price', 10, 4)->nullable();
            $table->tinyInteger('status')->default(1)->index();
            $table->timestamps();
        });

        Schema::create('country_pricing_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code');
            $table->string('conversation');
            $table->decimal('old_price', 10, 4)->nullable();
            $table->decimal('new_price', 10, 4)->nullable();
            $table->unsignedBigInteger('updated_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('country_code');
            $table->index('updated_by');
        });

        Schema::create('cloud_bill_uploads', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('filename');
            $table->string('path');
            $table->string('period')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_bill_uploads');
        Schema::dropIfExists('country_pricing_logs');
        Schema::dropIfExists('country_pricing');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('platform_settings');
    }
};
