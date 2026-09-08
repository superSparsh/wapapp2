<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Services\TemplateSyncService;
use Illuminate\Console\Command;

class SyncTemplateStatuses extends Command
{
    protected $signature = 'templates:sync-statuses {--limit=50 : Maximum templates to sync}';
    protected $description = 'Sync template approval statuses from the WhatsApp API.';

    public function handle(TemplateSyncService $syncService): int
    {
        $limit = (int) $this->option('limit');

        // First, sync the first pending template without a code (new submission)
        $syncService->syncFirstPending();

        // Then batch-sync templates that have a code and are still pending
        $result = $syncService->syncBatch($limit);

        $this->info(
            "Sync complete: {$result['processed']} processed, {$result['success']} succeeded, {$result['errors']} errors."
        );

        return self::SUCCESS;
    }
}
