<?php

declare(strict_types=1);

use App\Domains\Tenancy\Services\TenantIdMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TenantIdMigrator::class)->migrateAllToDomainSlugs();
    }

    public function down(): void
    {
        // Irreversible: tenant databases and foreign keys are renamed to slug IDs.
    }
};
