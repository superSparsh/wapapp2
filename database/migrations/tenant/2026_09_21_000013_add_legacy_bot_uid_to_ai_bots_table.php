<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Chroma bot_id is ai_bots.uid (PHP uniqid()), NOT numeric id.
 * See legacy ExternalApiService::resolveBotUidString / appendBotIdQuery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_bots', 'legacy_bot_uid')) {
                $table->string('legacy_bot_uid', 64)->nullable()->after('legacy_bot_id');
                $table->index('legacy_bot_uid', 'ai_bots_legacy_bot_uid_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            if (Schema::hasColumn('ai_bots', 'legacy_bot_uid')) {
                $table->dropIndex('ai_bots_legacy_bot_uid_idx');
                $table->dropColumn('legacy_bot_uid');
            }
        });
    }
};
