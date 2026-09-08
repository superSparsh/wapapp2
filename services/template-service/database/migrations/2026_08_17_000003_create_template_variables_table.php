<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->index();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('variables')->cascadeOnDelete();
            $table->string('placement', 20)->default('body');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_variables');
    }
};
