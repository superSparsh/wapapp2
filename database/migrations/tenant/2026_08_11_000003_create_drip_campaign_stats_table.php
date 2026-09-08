<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drip_campaign_stats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('drip_campaign_id')->constrained('drip_campaigns')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('node_id', 100)->nullable();
            $table->string('node_type', 50)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('action', 32)->default('entered');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('drip_campaign_id', 'drip_stats_campaign_idx');
            $table->index('conversation_id', 'drip_stats_conversation_idx');
            $table->index('created_at', 'drip_stats_created_idx');
            $table->index(['drip_campaign_id', 'action'], 'drip_stats_campaign_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_campaign_stats');
    }
};
