<?php

declare(strict_types=1);

namespace App\Domains\Audience\Console\Commands;

use App\Domains\Audience\Services\NonWhatsAppNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BackfillNonWhatsAppNumbersCommand extends Command
{
    protected $signature = 'audience:backfill-non-whatsapp
                            {--days= : Limit scan to the last N days}
                            {--tenant= : Tenant id (optional; runs for all tenants when omitted)}';

    protected $description = 'Backfill “non whatsapp number” tags from opt-in delivery errors (131026)';

    public function handle(NonWhatsAppNumberService $service): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $tenantId = $this->option('tenant');

        $run = function () use ($service, $days): void {
            $result = $service->backfillFromHistory($days);
            $this->info(sprintf(
                'Tenant %s — scanned: %d, marked: %d, skipped: %d',
                (string) (tenant('id') ?? 'central'),
                $result['scanned'],
                $result['marked'],
                $result['skipped'],
            ));
        };

        if (function_exists('tenancy') && class_exists(\App\Models\Tenant::class)) {
            $query = \App\Models\Tenant::query();
            if (filled($tenantId)) {
                $query->whereKey($tenantId);
            }

            $query->each(function ($tenant) use ($run): void {
                tenancy()->initialize($tenant);
                try {
                    $run();
                } finally {
                    tenancy()->end();
                }
            });

            return self::SUCCESS;
        }

        $run();

        return self::SUCCESS;
    }
}
