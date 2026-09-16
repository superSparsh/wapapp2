<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Services\TemplateSyncService;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class SyncTemplateStatuses extends Command
{
    use IteratesTenants;

    protected $signature = 'templates:sync-statuses
                            {--limit=50 : Maximum templates to sync per tenant}
                            {--coded : Sync all coded templates (status + category), for daily Meta category drift}
                            {--tenants=* : Tenant IDs to process}';

    protected $description = 'Sync template approval statuses (and categories) from the WhatsApp / Alibaba CAMS API.';

    public function handle(TemplateSyncService $syncService): int
    {
        $limit = (int) $this->option('limit');
        $totals = [
            'processed' => 0,
            'success' => 0,
            'category_updates' => 0,
            'errors' => 0,
        ];

        $this->foreachTenant(function () use ($syncService, $limit, &$totals): void {
            if ($this->option('coded')) {
                $result = $syncService->syncCodedDetailsBatch($limit);
            } else {
                $syncService->syncFirstPending();
                $result = $syncService->syncBatch($limit);
            }

            $totals['processed'] += (int) ($result['processed'] ?? 0);
            $totals['success'] += (int) ($result['success'] ?? 0);
            $totals['category_updates'] += (int) ($result['category_updates'] ?? 0);
            $totals['errors'] += (int) ($result['errors'] ?? 0);
        });

        $this->info(
            "Sync complete: {$totals['processed']} processed, {$totals['success']} succeeded, "
            ."{$totals['category_updates']} category updates, {$totals['errors']} errors."
        );

        return self::SUCCESS;
    }
}
