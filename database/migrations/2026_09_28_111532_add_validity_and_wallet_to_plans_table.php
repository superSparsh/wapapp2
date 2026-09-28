<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('validity_days')->nullable()->after('billing_cycle');
            $table->decimal('starting_wallet_balance', 12, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['validity_days', 'starting_wallet_balance']);
        });
    }
};
