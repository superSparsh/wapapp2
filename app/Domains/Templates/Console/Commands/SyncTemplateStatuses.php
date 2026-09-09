<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Services\TemplateSyncService;
use Illuminate\Console\Command;

class SyncTemplateStatuses extends Command
{
    protected $signature = 'templates:sync-statuses
                            {--limit=50 : Maximum templates to sync}
                            {--coded : Sync all coded templates (status + category), for daily Meta category drift}';

    protected $description = 'Sync template approval statuses (and categories) from the WhatsApp / Alibaba CAMS API.';

    public function handle(TemplateSyncService $syncService): int
    {
        $limit = (int) $this->option('limit');

        if ($this->option('coded')) {
            $result = $syncService->syncCodedDetailsBatch($limit);

            $this->info(
                "Coded sync complete: {$result['processed']} processed, {$result['success']} succeeded, "
                ."{$result['category_updates']} category updates, {$result['errors']} errors."
            );

            return self::SUCCESS;
        }

        // First, sync the first pending template without a code (new submission)
        $syncService->syncFirstPending();

        // Then batch-sync templates that have a code and are still pending
        $result = $syncService->syncBatch($limit);

        $this->info(
            "Sync complete: {$result['processed']} processed, {$result['success']} succeeded, "
            ."{$result['category_updates']} category updates, {$result['errors']} errors."
        );

        return self::SUCCESS;
    }
}
