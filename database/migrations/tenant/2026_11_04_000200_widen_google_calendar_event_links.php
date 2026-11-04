<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_calendar_events')) {
            return;
        }

        // Production MySQL: Google htmlLink / Meet URLs can exceed VARCHAR(255).
        // SQLite tests already get TEXT from the create migration — skip ALTER.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('google_calendar_events', 'meet_link')) {
            DB::statement('ALTER TABLE google_calendar_events MODIFY meet_link TEXT NULL');
        }
        if (Schema::hasColumn('google_calendar_events', 'calendar_link')) {
            DB::statement('ALTER TABLE google_calendar_events MODIFY calendar_link TEXT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_calendar_events')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('google_calendar_events', 'meet_link')) {
            DB::statement('ALTER TABLE google_calendar_events MODIFY meet_link VARCHAR(255) NULL');
        }
        if (Schema::hasColumn('google_calendar_events', 'calendar_link')) {
            DB::statement('ALTER TABLE google_calendar_events MODIFY calendar_link VARCHAR(255) NULL');
        }
    }
};
