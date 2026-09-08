<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id', 100)->index();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('external_message_id', 100)->nullable()->index();
            $table->text('body')->nullable();
            $table->string('direction', 20)->default('inbound');
            $table->string('message_type', 30)->default('text');
            $table->string('status', 30)->default('delivered');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('chatbot_flow_id')->nullable();
            $table->unsignedBigInteger('meta_flow_id')->nullable();
            $table->string('failed_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'conversation_id', 'created_at'], 'msg_tenant_conv_created_idx');
            $table->index(['tenant_id', 'external_message_id'], 'msg_tenant_ext_msg_idx');
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
