<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_status_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->index();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_status_logs');
    }
};
