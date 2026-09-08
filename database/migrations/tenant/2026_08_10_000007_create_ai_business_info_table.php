<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_business_info', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('content_type', 20)->default('text');
            $table->longText('content')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('embedding_status', 20)->default('pending');
            $table->string('embedding_id', 191)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('ai_bot_id', 'business_info_bot_idx');
            $table->index('embedding_status', 'business_info_embedding_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_business_info');
    }
};
