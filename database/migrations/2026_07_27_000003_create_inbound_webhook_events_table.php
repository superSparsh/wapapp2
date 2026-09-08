<?php

declare(strict_types=1);

use App\Enums\InboundWebhookStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 32)->index();
            $table->string('idempotency_key', 191);
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('whatsapp_line_id')->nullable();
            $table->string('status', 32)->default(InboundWebhookStatus::Received->value);
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_type', 'idempotency_key'], 'inbound_webhook_events_dedup_unique');
            $table->index(['status', 'created_at'], 'inbound_webhook_events_queue_index');
            $table->index(['tenant_id', 'created_at'], 'inbound_webhook_events_tenant_index');
            $table->index('processed_at', 'inbound_webhook_events_processed_index');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_events');
    }
};
