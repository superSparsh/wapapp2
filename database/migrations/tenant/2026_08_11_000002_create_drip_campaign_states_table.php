<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drip_campaign_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('drip_campaign_id')->constrained('drip_campaigns')->cascadeOnDelete();
            $table->string('current_node_id', 100)->nullable();
            $table->json('variables')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('drip_campaign_id', 'drip_states_campaign_idx');
            $table->index('conversation_id', 'drip_states_conversation_idx');
            $table->index(['drip_campaign_id', 'status'], 'drip_states_campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_campaign_states');
    }
};
