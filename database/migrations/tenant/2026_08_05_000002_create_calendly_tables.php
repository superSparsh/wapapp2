<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendly_integrations', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->string('status')->default('disabled');
            $table->json('settings')->nullable();
            $table->timestamp('first_synced_at')->nullable();
            $table->auditable();
        });

        Schema::create('calendly_events', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('event_id')->unique()->index();
            $table->string('status')->default('active');
            $table->string('event_type')->nullable();
            $table->timestamp('start_time')->nullable()->index();
            $table->timestamp('end_time')->nullable();
            $table->string('invitee_email')->nullable();
            $table->string('event_uri')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->json('form_responses')->nullable();
            $table->boolean('notified_created')->default(false);
            $table->boolean('notified_canceled')->default(false);
            $table->boolean('notified_rescheduled')->default(false);
            $table->auditable();
        });

        Schema::create('calendly_message_logs', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('recipient_type')->nullable();
            $table->string('recipient_number')->nullable();
            $table->string('invitee_email')->nullable();
            $table->string('event_name')->nullable();
            $table->string('event_type')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->auditable();
        });

        Schema::create('calendly_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->json('payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendly_webhook_logs');
        Schema::dropIfExists('calendly_message_logs');
        Schema::dropIfExists('calendly_events');
        Schema::dropIfExists('calendly_integrations');
    }
};
