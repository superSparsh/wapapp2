<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_flows', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('status', 32)->default('draft');
            $table->longText('exported_data')->nullable();
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->auditable();

            $table->index(['status', 'deleted_at'], 'chatbot_flows_status_deleted_idx');
            $table->index(['whatsapp_line_id', 'deleted_at'], 'chatbot_flows_line_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_flows');
    }
};
