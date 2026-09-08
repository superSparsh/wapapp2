<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_flow_stats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chatbot_flow_id')->constrained('chatbot_flows')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('node_id', 100)->nullable();
            $table->string('node_type', 50)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('action', 32)->default('entered');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('chatbot_flow_id', 'flow_stats_flow_idx');
            $table->index('conversation_id', 'flow_stats_conversation_idx');
            $table->index('created_at', 'flow_stats_created_idx');
            $table->index(['chatbot_flow_id', 'action'], 'flow_stats_flow_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_flow_stats');
    }
};
