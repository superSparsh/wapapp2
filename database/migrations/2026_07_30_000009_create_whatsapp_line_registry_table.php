<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_line_registry', function (Blueprint $table) {
            $table->id();
            $table->phoneNumber('phone', unique: true);
            $table->string('tenant_id');
            $table->unsignedBigInteger('line_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'line_id'], 'whatsapp_line_registry_tenant_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_line_registry');
    }
};
