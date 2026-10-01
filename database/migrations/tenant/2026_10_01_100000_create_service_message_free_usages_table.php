<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks Meta-style free service messages used per WhatsApp business phone (line) per calendar month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_message_free_usages', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->foreignId('whatsapp_line_id')->constrained('whatsapp_lines')->cascadeOnDelete();
            $table->char('year_month', 7); // YYYY-MM
            $table->unsignedInteger('used_count')->default(0);
            $table->auditable();

            $table->unique(['whatsapp_line_id', 'year_month'], 'svc_free_usage_line_month_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_message_free_usages');
    }
};
