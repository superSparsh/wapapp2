<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe tenant operational data while keeping the account + subscription
 * so the customer can be restored later without re-provisioning.
 */
class TenantAccountWipeService
{
    /**
     * Tenant DB tables that must survive an account wipe.
     *
     * @var list<string>
     */
    private const PRESERVE_TENANT_TABLES = [
        'migrations',
        'subscriptions',
        'users',
        'user_activations',
        'signup_consents',
    ];

    /**
     * Central tables keyed by tenant_id that should be cleared on wipe.
     *
     * @var list<string>
     */
    private const CENTRAL_TENANT_SCOPED_TABLES = [
        'whatsapp_line_registry',
        'message_external_index',
        'whatsapp_flow_exchange_registry',
        'inbound_webhook_events',
        'wa_health_snapshots',
        'wa_health_alerts',
        'shopify_webhook_events',
        'shopify_domain_registry',
        'renew_subscription_requests',
        'recharge_subscription_requests',
        'announcement_feature_requests',
        'platform_error_logs',
        'tenant_provisioning_logs',
    ];

    /**
     * @return array{tables_wiped: int, central_cleared: int}
     */
    public function wipe(Tenant $tenant, ?string $adminName = null): array
    {
        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        $tablesWiped = 0;
        $centralCleared = 0;

        try {
            tenancy()->initialize($tenant);

            $tablesWiped = $this->wipeTenantDatabase();
            $this->retainOwnerUsersOnly();
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($wasInitialized && $previous) {
                tenancy()->initialize($previous);
            }
        }

        $centralCleared = $this->clearCentralTenantScopedRows((string) $tenant->id);
        $this->markTenantWiped($tenant, $adminName);

        Log::info('admin.tenant.account_wiped', [
            'tenant_id' => $tenant->id,
            'admin' => $adminName,
            'tables_wiped' => $tablesWiped,
            'central_cleared' => $centralCleared,
        ]);

        return [
            'tables_wiped' => $tablesWiped,
            'central_cleared' => $centralCleared,
        ];
    }

    private function wipeTenantDatabase(): int
    {
        $connection = Schema::getConnection();
        $databaseName = (string) $connection->getDatabaseName();
        $tables = $this->listTables($connection, $databaseName);
        $preserve = array_fill_keys(self::PRESERVE_TENANT_TABLES, true);
        $wiped = 0;

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (isset($preserve[$table])) {
                    continue;
                }

                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->delete();
                $wiped++;
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $wiped;
    }

    private function retainOwnerUsersOnly(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        User::query()
            ->where('role', '!=', UserRole::Owner->value)
            ->delete();
    }

    private function clearCentralTenantScopedRows(string $tenantId): int
    {
        $cleared = 0;
        $central = DB::connection(config('tenancy.database.central_connection', config('database.default')));

        foreach (self::CENTRAL_TENANT_SCOPED_TABLES as $table) {
            if (! Schema::connection($central->getName())->hasTable($table)) {
                continue;
            }

            $cleared += $central->table($table)->where('tenant_id', $tenantId)->delete();
        }

        return $cleared;
    }

    private function markTenantWiped(Tenant $tenant, ?string $adminName): void
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['account_wiped_at'] = now()->toIso8601String();
        $settings['account_wiped_by'] = $adminName;
        unset($settings['purge_requested_at'], $settings['purge_requested_by']);

        $tenant->settings = $settings;
        $tenant->status = TenantStatus::Suspended;
        $tenant->suspended_at = $tenant->suspended_at ?? now();
        $tenant->save();
    }

    /**
     * @return list<string>
     */
    private function listTables($connection, string $databaseName): array
    {
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            );

            return array_values(array_filter(array_map(
                static fn ($row): string => (string) ($row->name ?? ''),
                $rows,
            )));
        }

        $rows = $connection->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$databaseName]
        );

        return array_values(array_filter(array_map(
            static fn ($row): string => (string) ($row->name ?? ''),
            $rows,
        )));
    }
}
