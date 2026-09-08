<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('body_preview');
        });

        Schema::create('template_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'created_at'], 'tpl_status_logs_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_status_logs');

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
