<?php

declare(strict_types=1);

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('webhook_subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->string('event_type', 100)->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->string('status', 32)->default(WebhookDeliveryStatus::Pending->value);
            $table->unsignedTinyInteger('attempt_count')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamps();

            $table->index(['webhook_subscription_id', 'created_at'], 'webhook_deliveries_subscription_index');
            $table->index(['status', 'next_retry_at'], 'webhook_deliveries_retry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
