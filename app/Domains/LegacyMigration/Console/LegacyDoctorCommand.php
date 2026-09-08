<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegacyDoctorCommand extends Command
{
    protected $signature = 'legacy:doctor';

    protected $description = 'Diagnose legacy DB connection/config for customer migration';

    public function handle(): int
    {
        $this->info('=== Legacy Migration Doctor ===');
        $this->newLine();

        $php = PHP_VERSION;
        $this->line("PHP version: {$php}");
        $this->line('pdo loaded: '.(extension_loaded('pdo') ? 'yes' : 'NO'));
        $this->line('pdo_mysql loaded: '.(extension_loaded('pdo_mysql') ? 'yes' : 'NO'));
        $this->line('mysqli loaded: '.(extension_loaded('mysqli') ? 'yes' : 'NO'));
        $this->newLine();

        $connectionName = (string) config('legacy-migration.connection', 'legacy');
        $this->line("Configured connection name: [{$connectionName}]");

        $connections = array_keys(config('database.connections', []));
        $this->line('Available connections: '.implode(', ', $connections));

        if (! array_key_exists($connectionName, config('database.connections', []))) {
            $this->error("Connection [{$connectionName}] is missing from config/database.php.");
            $this->line('Deploy latest code (legacy connection block) then: php artisan config:clear');

            return self::FAILURE;
        }

        $cfg = config('database.connections.'.$connectionName);
        $this->newLine();
        $this->info('Legacy connection settings (from config):');
        $this->table(['Key', 'Value'], [
            ['driver', $cfg['driver'] ?? ''],
            ['host', $cfg['host'] ?? ''],
            ['port', $cfg['port'] ?? ''],
            ['database', $cfg['database'] ?? ''],
            ['username', $cfg['username'] ?? ''],
            ['password', filled($cfg['password'] ?? null) ? '***set***' : '(empty)'],
        ]);

        $this->newLine();
        $this->info('Raw .env-related values:');
        $this->table(['Env', 'Value'], [
            ['LEGACY_DB_CONNECTION', env('LEGACY_DB_CONNECTION')],
            ['LEGACY_DB_HOST', env('LEGACY_DB_HOST')],
            ['LEGACY_DB_PORT', env('LEGACY_DB_PORT')],
            ['LEGACY_DB_DATABASE', env('LEGACY_DB_DATABASE')],
            ['LEGACY_DB_USERNAME', env('LEGACY_DB_USERNAME')],
            ['LEGACY_DB_PASSWORD', filled(env('LEGACY_DB_PASSWORD')) ? '***set***' : '(empty)'],
            ['DB_DATABASE (app master)', env('DB_DATABASE')],
        ]);

        if (($cfg['database'] ?? null) === env('DB_DATABASE')) {
            $this->warn('WARNING: LEGACY_DB_DATABASE is the same as DB_DATABASE (likely wapapp_master).');
            $this->warn('Legacy customers table lives in the OLD app DB (often named wapapp), not the new master DB.');
        }

        if (! extension_loaded('pdo_mysql') && ($cfg['driver'] ?? '') === 'mysql') {
            $this->error('pdo_mysql is NOT loaded for this PHP binary. MySQL connections will fail.');
            $this->line('Fix: sudo apt install php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-mysql');
            $this->line('Then: php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.' -m | grep pdo_mysql');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Trying PDO connect…');

        try {
            $pdo = DB::connection($connectionName)->getPdo();
            $this->line('Connected OK. Driver: '.$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        } catch (Throwable $exception) {
            $this->error('Connect FAILED: '.$exception->getMessage());
            $this->newLine();
            $this->line('Common fixes:');
            $this->line('1) Correct host/port/user/password in .env');
            $this->line('2) Allow remote MySQL user if legacy DB is on another server');
            $this->line('3) php artisan config:clear');

            return self::FAILURE;
        }

        try {
            $dbName = DB::connection($connectionName)->selectOne('select database() as db');
            $this->line('Selected database(): '.($dbName->db ?? 'null'));
        } catch (Throwable $exception) {
            $this->warn('Could not read database(): '.$exception->getMessage());
        }

        $required = ['customers', 'users', 'new_contacts'];
        $this->newLine();
        $this->info('Required tables:');
        foreach ($required as $table) {
            $exists = Schema::connection($connectionName)->hasTable($table);
            $this->line(($exists ? '[OK] ' : '[MISSING] ').$table);
        }

        $missing = array_filter($required, fn (string $t) => ! Schema::connection($connectionName)->hasTable($t));
        if ($missing !== []) {
            $this->newLine();
            $this->error('Missing tables: '.implode(', ', $missing));
            $this->line('You are connected, but this is not the legacy WapApp schema.');
            $this->line('List databases: mysql -u... -p -e "SHOW DATABASES;"');
            $this->line('Find customers: mysql -u... -p -e "SELECT table_schema FROM information_schema.tables WHERE table_name=\'customers\';"');

            try {
                $tables = DB::connection($connectionName)->select('SHOW TABLES');
                $names = [];
                foreach ($tables as $row) {
                    $names[] = array_values((array) $row)[0];
                }
                $this->line('Tables in current DB (first 30): '.implode(', ', array_slice($names, 0, 30)));
            } catch (Throwable) {
                // ignore
            }

            return self::FAILURE;
        }

        try {
            $count = DB::connection($connectionName)->table('customers')->count();
            $this->newLine();
            $this->info("Success. customers count = {$count}");
            $this->line('Next: php artisan legacy:list-customers');
        } catch (Throwable $exception) {
            $this->error('customers query failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
