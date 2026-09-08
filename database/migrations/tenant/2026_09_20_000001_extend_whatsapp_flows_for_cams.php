<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_flows', 'exchange_token')) {
                $table->string('exchange_token', 64)->nullable()->unique()->after('data_exchange_endpoint');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'categories')) {
                $table->json('categories')->nullable()->after('name');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'json_asset_path')) {
                $table->string('json_asset_path', 500)->nullable()->after('flow_json');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'meta_json')) {
                $table->longText('meta_json')->nullable()->after('json_asset_path');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'draft_synced_at')) {
                $table->timestamp('draft_synced_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'cust_space_id')) {
                $table->string('cust_space_id', 64)->nullable()->after('whatsapp_line_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $columns = ['exchange_token', 'categories', 'json_asset_path', 'meta_json', 'draft_synced_at', 'cust_space_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('whatsapp_flows', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
