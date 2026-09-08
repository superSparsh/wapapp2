<?php

declare(strict_types=1);

use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantProvisioner;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('database_name', 64)->nullable()->unique()->after('id');
        });

        $namingService = app(TenantDatabaseNamingService::class);
        $provisioner = app(TenantProvisioner::class);

        Tenant::query()->each(function (Tenant $tenant) use ($namingService, $provisioner): void {
            $databaseName = $namingService->forTenantId($tenant->getTenantKey());

            $tenant->forceFill(['database_name' => $databaseName])->save();
            $tenant->setInternal('db_name', $databaseName);
            $tenant->save();

            $provisioner->ensureDatabase($tenant->refresh());
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['database_name']);
            $table->dropColumn('database_name');
        });
    }
};
