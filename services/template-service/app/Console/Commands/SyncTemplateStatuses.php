<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TemplateSyncService;
use Illuminate\Console\Command;

class SyncTemplateStatuses extends Command
{
    protected $signature = 'templates:sync-statuses
                            {--limit=50 : Maximum templates to sync}
                            {--coded : Sync all coded templates (status + category)}';

    protected $description = 'Sync template approval statuses from Alibaba CAMS.';

    public function handle(TemplateSyncService $syncService): int
    {
        $limit = (int) $this->option('limit');

        if ($this->option('coded')) {
            $result = $syncService->syncCodedDetailsBatch($limit);
        } else {
            $syncService->syncFirstPending();
            $result = $syncService->syncBatch($limit);
        }

        $this->info(
            'Sync complete: '.(int) ($result['processed'] ?? 0).' processed, '
            .(int) ($result['success'] ?? 0).' succeeded, '
            .(int) ($result['category_updates'] ?? 0).' category updates, '
            .(int) ($result['errors'] ?? 0).' errors.'
        );

        return self::SUCCESS;
    }
}
