<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_access', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('tenant_id')->index();
            $table->string('account_type', 16)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_access');
    }
};
