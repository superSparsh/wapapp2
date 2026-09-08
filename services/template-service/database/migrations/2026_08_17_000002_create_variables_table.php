<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id', 64)->index();
            $table->string('type', 20)->default('dynamic');
            $table->string('name');
            $table->string('data_type', 20);
            $table->text('value')->nullable();
            $table->unsignedBigInteger('whatsapp_line_id')->nullable();
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->string('team_member_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'whatsapp_line_id']);
            $table->index(['tenant_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variables');
    }
};
