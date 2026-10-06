<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_recipients')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                if (Schema::hasColumn('campaign_recipients', 'message_id')) {
                    $table->index('message_id', 'camp_recipients_message_id_idx');
                    $table->index(['message_id', 'contact_phone'], 'camp_recipients_msg_phone_idx');
                }
            });
        }

        if (Schema::hasTable('form_submissions')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                if (Schema::hasColumn('form_submissions', 'external_message_id')) {
                    $table->index('external_message_id', 'form_submissions_external_msg_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaign_recipients')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->dropIndex('camp_recipients_message_id_idx');
                $table->dropIndex('camp_recipients_msg_phone_idx');
            });
        }

        if (Schema::hasTable('form_submissions')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                $table->dropIndex('form_submissions_external_msg_idx');
            });
        }
    }
};
