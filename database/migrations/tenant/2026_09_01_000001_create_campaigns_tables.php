<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name', 255);
            $table->string('status', 20)->default('draft');
            $table->foreignId('audience_id')->nullable()->constrained('mail_lists')->nullOnDelete();
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->json('template_variables')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('total_delivered')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedInteger('total_read')->default(0);
            $table->unsignedInteger('total_unsubscribed')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->auditable();

            $table->index(['status', 'created_at'], 'campaigns_status_created_idx');
            $table->index(['audience_id', 'status'], 'campaigns_audience_status_idx');
            $table->index(['whatsapp_line_id', 'status'], 'campaigns_line_status_idx');
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('contact_phone', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('message_id', 100)->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status'], 'camp_recipients_campaign_status_idx');
            $table->unique(['campaign_id', 'contact_id'], 'camp_recipients_campaign_contact_unique');
            $table->index(['contact_phone', 'status'], 'camp_recipients_phone_status_idx');
            $table->index(['campaign_id', 'created_at'], 'camp_recipients_campaign_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
