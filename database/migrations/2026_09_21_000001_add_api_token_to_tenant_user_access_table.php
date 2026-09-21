<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_user_access', function (Blueprint $table): void {
            $table->string('api_token', 80)->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_user_access', function (Blueprint $table): void {
            $table->dropUnique(['api_token']);
            $table->dropColumn('api_token');
        });
    }
};
