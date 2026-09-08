<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class TenantProvisioner
{
    public function __construct(
        private readonly TenantDatabaseNamingService $namingService,
    ) {}

    public function assignDatabaseName(Tenant $tenant): Tenant
    {
        $databaseName = $this->namingService->forTenantId($tenant->getTenantKey());

        if ($tenant->database_name !== $databaseName) {
            $tenant->forceFill(['database_name' => $databaseName])->save();
        }

        $tenant->setInternal('db_name', $databaseName);
        $tenant->save();

        return $tenant->refresh();
    }

    public function ensureDatabase(Tenant $tenant): Tenant
    {
        $tenant = $this->assignDatabaseName($tenant);

        $manager = $tenant->database()->manager();
        $databaseName = $tenant->database_name;

        if ($databaseName !== null && $manager->databaseExists($databaseName)) {
            return $tenant;
        }

        foreach ($this->namingService->legacyDatabaseNames($tenant->getTenantKey()) as $legacyName) {
            if ($manager->databaseExists($legacyName)) {
                $this->renameDatabase($legacyName, (string) $databaseName);

                return $tenant->refresh();
            }
        }

        $tenant->database()->makeCredentials();
        (new CreateDatabase($tenant))->handle();
        (new MigrateDatabase($tenant))->handle();

        return $tenant->refresh();
    }

    private function renameDatabase(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $charset = config('database.connections.'.config('database.default').'.charset', 'utf8mb4');
        $collation = config('database.connections.'.config('database.default').'.collation', 'utf8mb4_unicode_ci');

        \Illuminate\Support\Facades\DB::statement(
            "CREATE DATABASE `{$to}` CHARACTER SET `{$charset}` COLLATE `{$collation}`"
        );

        $tables = \Illuminate\Support\Facades\DB::select(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
            [$from],
        );

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            \Illuminate\Support\Facades\DB::statement(
                "RENAME TABLE `{$from}`.`{$tableName}` TO `{$to}`.`{$tableName}`"
            );
        }

        \Illuminate\Support\Facades\DB::statement("DROP DATABASE `{$from}`");
    }

    public function migrate(Tenant $tenant): void
    {
        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->getTenantKey()],
            '--force' => true,
        ]);
    }
}
