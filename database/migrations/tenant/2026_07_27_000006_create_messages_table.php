<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('external_message_id', 191)->nullable();
            $table->text('body')->nullable();
            $table->string('direction', 16)->default(MessageDirection::Inbound->value);
            $table->string('message_type', 32)->default(MessageType::Text->value);
            $table->string('status', 32)->default(MessageStatus::Pending->value);
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->unsignedBigInteger('chatbot_flow_id')->nullable()->index();
            $table->unsignedBigInteger('meta_flow_id')->nullable()->index();
            $table->text('failed_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('external_message_id', 'messages_external_id_unique');
            $table->index(['conversation_id', 'created_at'], 'messages_thread_index');
            $table->index(['status', 'direction'], 'messages_status_direction_index');
            $table->index(['external_message_id', 'status'], 'messages_delivery_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
