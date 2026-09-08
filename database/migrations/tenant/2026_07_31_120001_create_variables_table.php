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
            $table->string('type', 20)->default('dynamic');
            $table->string('name');
            $table->string('data_type', 20);
            $table->text('value')->nullable();
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->string('team_member_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'whatsapp_line_id', 'team_member_id'], 'variables_name_scope_uq');
            $table->index(['team_member_id', 'deleted_at'], 'variables_team_idx');
            $table->index('created_at', 'variables_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variables');
    }
};
