<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('language', 20)->default('en_GB');
            $table->string('category', 40)->default('MARKETING');
            $table->string('status', 30)->default('draft');
            $table->string('source', 20)->default('local');
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->string('team_member_name')->nullable();
            $table->json('payload')->nullable();
            $table->text('body_preview')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'whatsapp_line_id'], 'templates_code_line_uq');
            $table->index(['status', 'source', 'deleted_at'], 'templates_status_idx');
            $table->index('whatsapp_line_id', 'templates_line_idx');
        });

        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('variables')->cascadeOnDelete();
            $table->string('placement', 20)->default('body');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['template_id', 'variable_id', 'placement'], 'template_variables_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_variables');
        Schema::dropIfExists('templates');
    }
};
