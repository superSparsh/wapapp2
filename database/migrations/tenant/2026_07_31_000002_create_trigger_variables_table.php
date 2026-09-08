<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trigger_variables', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('variable_name', 191);
            $table->string('template_code', 255);
            $table->string('template_name', 255);
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->unsignedBigInteger('list_id')->nullable();
            $table->string('list_name', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('variable_name', 'trigger_variables_name_uq');
            $table->index(['whatsapp_line_id', 'deleted_at'], 'trigger_variables_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trigger_variables');
    }
};
