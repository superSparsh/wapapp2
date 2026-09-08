<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('whatsapp_line_id')->constrained('whatsapp_lines')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->phoneNumber('contact_phone');
            $table->phoneNumber('line_phone');
            $table->string('contact_name', 150)->nullable();
            $table->string('status', 32)->default(ConversationStatus::Open->value);
            $table->string('response_type', 32)->default('human_response');
            $table->unsignedBigInteger('active_ai_bot_id')->nullable()->index();
            $table->unsignedSmallInteger('lead_score')->default(0);
            $table->string('qualification_status', 32)->default('pending')->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->auditable();

            $table->unique(['whatsapp_line_id', 'contact_phone'], 'conversations_line_contact_unique');
            $table->index(['status', 'last_message_at'], 'conversations_inbox_index');
            $table->index(['assigned_user_id', 'status'], 'conversations_agent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
