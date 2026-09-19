<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy parity: same phone may belong to multiple mail lists
 * (unique per list, not globally).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->unique(['phone', 'mail_list_id'], 'contacts_phone_mail_list_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_phone_mail_list_unique');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->unique('phone');
        });
    }
};
