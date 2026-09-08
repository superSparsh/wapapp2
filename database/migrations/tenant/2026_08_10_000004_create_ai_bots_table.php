<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bots', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('type', 50)->nullable();
            $table->text('system_prompt')->nullable();
            $table->string('provider', 20)->default('openai');
            $table->string('chat_model', 100)->default('gpt-4o-mini');
            $table->string('embedding_model', 100)->default('text-embedding-3-small');
            $table->float('temperature')->default(0.3);
            $table->longText('business_information')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_default')->default(false);
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->auditable();

            $table->index(['status', 'is_default'], 'ai_bots_status_default_idx');
            $table->index('whatsapp_line_id', 'ai_bots_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bots');
    }
};
