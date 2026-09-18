<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('country_pricing') || ! Schema::hasColumn('country_pricing', 'uuid')) {
            return;
        }

        try {
            Schema::table('country_pricing', function (Blueprint $table): void {
                $table->dropUnique(['uuid']);
            });
        } catch (\Throwable) {
            // Unique index may already be absent.
        }

        Schema::table('country_pricing', function (Blueprint $table): void {
            $table->dropColumn('uuid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('country_pricing') || Schema::hasColumn('country_pricing', 'uuid')) {
            return;
        }

        Schema::table('country_pricing', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
        });
    }
};
