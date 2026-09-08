<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_lines', function (Blueprint $table) {
            // Bcrypt-hashed password that allows "Login as this number" (per-line Inbox context).
            // NULL = no password set yet.
            $table->string('line_password', 255)->nullable()->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_lines', function (Blueprint $table) {
            $table->dropColumn('line_password');
        });
    }
};
