<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_customer_migrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_customer_id')->unique();
            $table->string('legacy_customer_uid', 64)->nullable()->index();
            $table->string('legacy_email', 191)->nullable()->index();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->json('preview')->nullable();
            $table->json('report')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_customer_migrations');
    }
};
