<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->string('question_key', 64);
            $table->string('answer', 32);
            $table->json('meta')->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->index(['user_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_consents');
    }
};
