<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Domain;

class TenantIdMigrator
{
    public function __construct(
        private readonly TenantDatabaseNamingService $namingService,
    ) {}

    public function migrateAllToDomainSlugs(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            $slug = Domain::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_primary', true)
                ->value('domain');

            if (! is_string($slug) || $slug === '' || $slug === $tenant->id) {
                return;
            }

            $this->migrateTenant($tenant->id, $slug);
        });
    }

    public function migrateTenant(string $oldId, string $newId): void
    {
        $oldDatabase = $this->namingService->forTenantId($oldId);
        $newDatabase = $this->namingService->forTenantId($newId);

        if ($oldDatabase !== $newDatabase && $this->databaseExists($oldDatabase)) {
            if (! $this->databaseExists($newDatabase)) {
                $this->createDatabaseLike($newDatabase, $oldDatabase);
                $this->moveTables($oldDatabase, $newDatabase);
            }

            if ($this->tableCount($oldDatabase) === 0) {
                DB::statement("DROP DATABASE `{$oldDatabase}`");
            }
        }

        Schema::disableForeignKeyConstraints();

        try {
            DB::table('domains')->where('tenant_id', $oldId)->update(['tenant_id' => $newId]);
            DB::table('tenant_user_access')->where('tenant_id', $oldId)->update(['tenant_id' => $newId]);
            DB::table('tenant_provisioning_logs')->where('tenant_id', $oldId)->update(['tenant_id' => $newId]);
            DB::table('inbound_webhook_events')->where('tenant_id', $oldId)->update(['tenant_id' => $newId]);
            DB::table('tenants')->where('id', $oldId)->update(['id' => $newId]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function databaseExists(string $name): bool
    {
        $result = DB::select(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name],
        );

        return $result !== [];
    }

    private function createDatabaseLike(string $newDatabase, string $sourceDatabase): void
    {
        $charset = DB::connection()->getConfig('charset') ?? 'utf8mb4';
        $collation = DB::connection()->getConfig('collation') ?? 'utf8mb4_unicode_ci';

        DB::statement("CREATE DATABASE `{$newDatabase}` CHARACTER SET `{$charset}` COLLATE `{$collation}`");
    }

    private function moveTables(string $fromDatabase, string $toDatabase): void
    {
        $tables = DB::select(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
            [$fromDatabase],
        );

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            DB::statement("RENAME TABLE `{$fromDatabase}`.`{$tableName}` TO `{$toDatabase}`.`{$tableName}`");
        }
    }

    private function tableCount(string $database): int
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
            [$database],
        );

        return (int) ($result[0]->aggregate ?? 0);
    }
}
