<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_external_index', function (Blueprint $table) {
            $table->id();
            $table->string('external_message_id', 191);
            $table->string('tenant_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('external_message_id', 'message_external_index_external_unique');
            $table->index(['tenant_id', 'created_at'], 'message_external_index_tenant_index');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_external_index');
    }
};
