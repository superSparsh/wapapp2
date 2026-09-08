<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_flow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('chatbot_flow_id')->constrained('chatbot_flows')->cascadeOnDelete();
            $table->string('current_node_id', 100);
            $table->json('variables')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status'], 'flow_states_conversation_status_idx');
            $table->index('chatbot_flow_id', 'flow_states_flow_idx');
            $table->index('expires_at', 'flow_states_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_flow_states');
    }
};
