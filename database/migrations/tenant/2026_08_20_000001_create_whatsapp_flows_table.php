<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('status', 32)->default('draft');
            $table->string('meta_flow_id', 191)->nullable();
            $table->longText('flow_json')->nullable();
            $table->string('data_exchange_endpoint', 500)->nullable();
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('audience_id')->nullable()->constrained('mail_lists')->nullOnDelete();
            $table->string('on_submit_action', 50)->nullable();
            $table->string('on_submit_webhook_url', 500)->nullable();
            $table->auditable();

            $table->index(['status', 'deleted_at'], 'whatsapp_flows_status_deleted_idx');
            $table->index('meta_flow_id', 'whatsapp_flows_meta_flow_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
    }
};
