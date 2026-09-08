<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drip_campaigns', function (Blueprint $table) {
            $table->json('trigger_options')->nullable()->after('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::table('drip_campaigns', function (Blueprint $table) {
            $table->dropColumn('trigger_options');
        });
    }
};
