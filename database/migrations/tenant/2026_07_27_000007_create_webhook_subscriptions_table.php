<?php

declare(strict_types=1);

use App\Enums\WebhookSubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->string('url', 500);
            $table->string('description', 255)->nullable();
            $table->string('secret_key', 255);
            $table->json('events');
            $table->string('status', 32)->default(WebhookSubscriptionStatus::Active->value);
            $table->unsignedBigInteger('audience_list_id')->nullable()->index();
            $table->timestamp('last_triggered_at')->nullable();
            $table->auditable();

            $table->index(['status', 'whatsapp_line_id'], 'webhook_subscriptions_active_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
