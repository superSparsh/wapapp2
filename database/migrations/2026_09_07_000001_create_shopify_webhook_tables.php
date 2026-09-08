<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_domain_registry', function (Blueprint $table): void {
            $table->id();
            $table->string('shop_domain')->unique();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('shopify_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('shop_domain')->nullable()->index();
            $table->string('topic')->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_webhook_events');
        Schema::dropIfExists('shopify_domain_registry');
    }
};
