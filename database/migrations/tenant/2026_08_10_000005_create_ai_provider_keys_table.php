<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_keys', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('provider', 20);
            $table->text('api_key');
            $table->string('chat_model', 100)->nullable();
            $table->string('embedding_model', 100)->nullable();
            $table->unsignedInteger('embedding_dimensions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_validated')->default(false);
            $table->timestamps();

            $table->index(['provider', 'is_active'], 'provider_keys_provider_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_keys');
    }
};
