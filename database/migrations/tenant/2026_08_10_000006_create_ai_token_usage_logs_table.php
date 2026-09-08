<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_bot_id')->nullable()->constrained('ai_bots')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('model', 100);
            $table->string('request_type', 20);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('embedding_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 8)->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->timestamps();

            $table->index('ai_bot_id', 'token_usage_bot_idx');
            $table->index('created_at', 'token_usage_created_idx');
            $table->index('provider', 'token_usage_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_usage_logs');
    }
};
