<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_payments')) {
            return;
        }

        Schema::table('commerce_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('commerce_payments', 'commerce_order_id')) {
                $table->unsignedBigInteger('commerce_order_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commerce_payments') || ! Schema::hasColumn('commerce_payments', 'commerce_order_id')) {
            return;
        }

        Schema::table('commerce_payments', function (Blueprint $table): void {
            $table->dropColumn('commerce_order_id');
        });
    }
};
