<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_integrations', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->string('status')->default('disabled');
            $table->json('settings')->nullable();
            $table->auditable();
        });

        Schema::create('shopify_send_data', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_send_data');
        Schema::dropIfExists('shopify_integrations');
    }
};
