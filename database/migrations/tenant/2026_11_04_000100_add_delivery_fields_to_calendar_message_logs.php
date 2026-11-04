<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendly_message_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendly_message_logs', 'external_message_id')) {
                $table->string('external_message_id')->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('calendly_message_logs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
        });

        Schema::table('google_calendar_message_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('google_calendar_message_logs', 'external_message_id')) {
                $table->string('external_message_id')->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('google_calendar_message_logs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendly_message_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('calendly_message_logs', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('calendly_message_logs', 'external_message_id')) {
                $table->dropColumn('external_message_id');
            }
        });

        Schema::table('google_calendar_message_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('google_calendar_message_logs', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('google_calendar_message_logs', 'external_message_id')) {
                $table->dropColumn('external_message_id');
            }
        });
    }
};
