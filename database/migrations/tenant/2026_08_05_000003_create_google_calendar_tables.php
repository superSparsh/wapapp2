<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_integrations', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->string('status')->default('disabled');
            $table->json('settings')->nullable();
            $table->timestamp('first_synced_at')->nullable();
            $table->auditable();
        });

        Schema::create('google_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('event_id')->index();
            $table->string('calendar_id')->default('primary');
            $table->string('status')->default('active');
            $table->string('summary')->nullable();
            $table->timestamp('start_time')->nullable()->index();
            $table->timestamp('end_time')->nullable();
            $table->string('invitee_email')->nullable();
            $table->string('invitee_name')->nullable();
            $table->string('meet_link')->nullable();
            $table->string('calendar_link')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('attendee_payload')->nullable();
            $table->boolean('notified_created')->default(false);
            $table->boolean('notified_canceled')->default(false);
            $table->boolean('notified_rescheduled')->default(false);
            $table->json('reminder_days_sent')->nullable();
            $table->timestamp('previous_start_time')->nullable();
            $table->auditable();

            $table->unique(['user_id', 'event_id', 'calendar_id']);
        });

        Schema::create('google_calendar_booking_links', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('slug')->unique();
            $table->string('title');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('status')->default('enabled');
            $table->json('settings')->nullable();
            $table->auditable();
        });

        Schema::create('google_calendar_message_logs', function (Blueprint $table): void {
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

        Schema::create('google_calendar_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_webhook_logs');
        Schema::dropIfExists('google_calendar_message_logs');
        Schema::dropIfExists('google_calendar_booking_links');
        Schema::dropIfExists('google_calendar_events');
        Schema::dropIfExists('google_calendar_integrations');
    }
};
