<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->index();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('secret_key', 64)->nullable();
            $table->json('events')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'campaign_id', 'status'], 'camp_webhooks_tenant_camp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_webhooks');
    }
};
