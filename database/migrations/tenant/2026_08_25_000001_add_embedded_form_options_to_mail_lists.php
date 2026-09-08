<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_lists', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_lists', 'embedded_form_options')) {
                $table->json('embedded_form_options')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_lists', function (Blueprint $table) {
            if (Schema::hasColumn('mail_lists', 'embedded_form_options')) {
                $table->dropColumn('embedded_form_options');
            }
        });
    }
};
