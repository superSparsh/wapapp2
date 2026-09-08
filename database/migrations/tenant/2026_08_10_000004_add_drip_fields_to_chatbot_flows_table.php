<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_flows', function (Blueprint $table) {
            $table->string('type', 20)->default('chatbot')->after('name');
            $table->string('timezone', 64)->nullable()->default('Asia/Kolkata')->after('published_at');
            $table->timestamp('start_date')->nullable()->after('timezone');
            $table->timestamp('end_date')->nullable()->after('start_date');
            $table->foreignId('audience_id')->nullable()->after('end_date')->constrained('mail_lists')->nullOnDelete();
            $table->string('trigger_type', 50)->nullable()->after('audience_id');

            $table->index(['type', 'status', 'deleted_at'], 'chatbot_flows_type_status_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_flows', function (Blueprint $table) {
            $table->dropIndex('chatbot_flows_type_status_deleted_idx');
            $table->dropConstrainedForeignId('audience_id');
            $table->dropColumn(['type', 'timezone', 'start_date', 'end_date', 'trigger_type']);
        });
    }
};
