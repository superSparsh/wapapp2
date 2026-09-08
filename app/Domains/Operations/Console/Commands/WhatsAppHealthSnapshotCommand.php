<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WhatsAppHealthSnapshotCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'operations:whatsapp-health-snapshot {--tenants=* : Tenant IDs to process}';

    protected $description = 'Capture WhatsApp health metrics snapshot for all tenants.';

    public function handle(): int
    {
        $this->foreachTenant(function ($tenant): void {
            Log::info('WhatsAppHealthSnapshotCommand: stub snapshot', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('WhatsApp health snapshot completed (stub).');

        return self::SUCCESS;
    }
}
