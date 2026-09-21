<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_bot_id')->nullable()->after('id');
            $table->index('legacy_bot_id', 'ai_bots_legacy_bot_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->dropIndex('ai_bots_legacy_bot_id_idx');
            $table->dropColumn('legacy_bot_id');
        });
    }
};
