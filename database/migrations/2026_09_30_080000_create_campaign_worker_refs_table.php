<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global sequential integers for OCI campaign-worker refcounting.
 * Meaning: auto-increment order of campaign creation across all tenants (not random).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_worker_refs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 255)->index();
            $table->unsignedBigInteger('tenant_campaign_id')->nullable()->index();
            $table->uuid('campaign_uuid')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'tenant_campaign_id'], 'campaign_worker_refs_tenant_campaign_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_worker_refs');
    }
};
