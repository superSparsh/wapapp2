<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id', 100)->index();
            $table->unsignedBigInteger('whatsapp_line_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_team_member_id')->nullable()->index();
            $table->string('contact_phone', 30)->index();
            $table->string('line_phone', 30)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('status', 50)->default('open');
            $table->string('response_type', 50)->default('human_response');
            $table->unsignedBigInteger('active_ai_bot_id')->nullable();
            $table->integer('lead_score')->default(0);
            $table->string('qualification_status', 50)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'whatsapp_line_id', 'last_message_at'], 'conv_tenant_line_last_msg_idx');
            $table->index(['tenant_id', 'contact_phone'], 'conv_tenant_contact_phone_idx');
            $table->index(['tenant_id', 'unread_count'], 'conv_tenant_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
