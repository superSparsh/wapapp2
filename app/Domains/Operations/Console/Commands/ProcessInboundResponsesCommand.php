<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessInboundResponsesCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'operations:process-inbound-responses {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process inbound WhatsApp responses (placeholder for response handler).';

    public function handle(): int
    {
        $this->foreachTenant(function ($tenant): void {
            Log::info('ProcessInboundResponsesCommand: no-op placeholder', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('Inbound response processing completed (placeholder).');

        return self::SUCCESS;
    }
}
