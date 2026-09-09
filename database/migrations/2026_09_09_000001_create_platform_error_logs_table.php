<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64)->index();
            $table->string('type', 32)->index(); // exception | api | job
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('source', 255)->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['module', 'type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_error_logs');
    }
};
