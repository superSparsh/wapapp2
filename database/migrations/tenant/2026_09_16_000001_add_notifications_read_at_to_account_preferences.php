<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_preferences', function (Blueprint $table): void {
            $table->timestamp('notifications_read_at')->nullable()->after('alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('account_preferences', function (Blueprint $table): void {
            $table->dropColumn('notifications_read_at');
        });
    }
};
