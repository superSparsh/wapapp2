<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('total_response')->default(0)->after('total_read');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn('responded_at');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('total_response');
        });
    }
};
