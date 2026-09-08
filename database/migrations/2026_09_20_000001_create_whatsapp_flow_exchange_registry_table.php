<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_flow_exchange_registry')) {
            return;
        }

        Schema::create('whatsapp_flow_exchange_registry', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_token', 64)->unique();
            $table->string('tenant_id', 64)->index();
            $table->unsignedBigInteger('flow_id');
            $table->timestamps();

            $table->index(['tenant_id', 'flow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_exchange_registry');
    }
};
