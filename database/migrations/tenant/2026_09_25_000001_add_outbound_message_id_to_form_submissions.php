<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('form_submissions', 'outbound_message_id')) {
                $table->unsignedBigInteger('outbound_message_id')->nullable()->after('external_message_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('form_submissions', 'outbound_message_id')) {
                $table->dropColumn('outbound_message_id');
            }
        });
    }
};
