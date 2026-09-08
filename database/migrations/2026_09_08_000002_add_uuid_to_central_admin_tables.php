<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['plans', 'announcements', 'country_pricing', 'cloud_bill_uploads'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uuid')->nullable()->after('id');
            });

            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'uuid' => (string) Str::uuid(),
                    ]);
                }
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['uuid']);
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
