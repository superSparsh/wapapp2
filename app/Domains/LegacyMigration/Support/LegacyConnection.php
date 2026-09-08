<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class LegacyConnection
{
    public function name(): string
    {
        return (string) config('legacy-migration.connection', 'legacy');
    }

    public function db(): \Illuminate\Database\Connection
    {
        return DB::connection($this->name());
    }

    public function assertReady(): void
    {
        if (! extension_loaded('pdo_mysql') && $this->driver() === 'mysql') {
            throw new RuntimeException(
                'PHP extension pdo_mysql is not loaded. Install/enable it for this PHP version, then retry. '.
                'Example (Ubuntu/Debian): sudo apt install php8.4-mysql && sudo phpenmod pdo_mysql'
            );
        }

        try {
            $this->db()->getPdo();
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Legacy database connection failed ('.$this->describeTarget().'): '.$exception->getMessage(),
                previous: $exception,
            );
        }

        foreach (['customers', 'users', 'new_contacts'] as $table) {
            if (! Schema::connection($this->name())->hasTable($table)) {
                throw new RuntimeException(
                    "Legacy table [{$table}] was not found on {$this->describeTarget()}. ".
                    'Check LEGACY_DB_DATABASE points to the old WapApp DB (usually `wapapp`), not wapapp_master.'
                );
            }
        }
    }

    public function tableExists(string $table): bool
    {
        return Schema::connection($this->name())->hasTable($table);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return Schema::connection($this->name())->hasColumn($table, $column);
    }

    public function describeTarget(): string
    {
        $config = config('database.connections.'.$this->name(), []);

        return sprintf(
            'connection=%s driver=%s host=%s port=%s database=%s',
            $this->name(),
            $config['driver'] ?? 'n/a',
            $config['host'] ?? 'n/a',
            $config['port'] ?? 'n/a',
            $config['database'] ?? 'n/a',
        );
    }

    private function driver(): string
    {
        return (string) (config('database.connections.'.$this->name().'.driver') ?? '');
    }
}
