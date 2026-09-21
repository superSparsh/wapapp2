<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waba_accounts', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('waba_id', 64)->nullable()->index();
            $table->string('alibaba_cust_space_id', 64)->nullable()->index();
            $table->boolean('is_registered')->default(false)->index();
            $table->json('waba_response')->nullable();
            $table->json('metadata')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waba_accounts');
    }
};
