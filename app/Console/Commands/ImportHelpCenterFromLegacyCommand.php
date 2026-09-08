<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HelpCenter\Services\HelpCenterLegacyImportService;
use Illuminate\Console\Command;

class ImportHelpCenterFromLegacyCommand extends Command
{
    protected $signature = 'help-center:import-legacy
                            {--fresh : Clear existing FAQs/tutorials before importing}
                            {--force : Skip confirmation prompts}
                            {--faqs-only : Import FAQs only}
                            {--tutorials-only : Import tutorials only}
                            {--copy-videos : Copy local tutorial MP4 files from legacy public assets}
                            {--dry-run : Show how many records would be imported}';

    protected $description = 'Import FAQs and tutorial videos from the legacy WapApp database';

    public function handle(HelpCenterLegacyImportService $importService): int
    {
        $importFaqs = ! $this->option('tutorials-only');
        $importTutorials = ! $this->option('faqs-only');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $importService->assertLegacyConnection();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $stats = $importService->import(
                fresh: false,
                importFaqs: $importFaqs,
                importTutorials: $importTutorials,
                copyVideos: (bool) $this->option('copy-videos'),
                dryRun: true,
            );

            $this->info('Dry run complete.');
            $this->line('FAQs: '.$stats['faqs']);
            $this->line('Tutorials: '.$stats['tutorials']);
            $this->line('Local videos to copy: '.$stats['videos_copied']);

            return self::SUCCESS;
        }

        if ($this->option('fresh') && ! $this->option('force') && ! $this->confirm('This will delete existing help-center records before import. Continue?')) {
            $this->warn('Import cancelled.');

            return self::SUCCESS;
        }

        $stats = $importService->import(
            fresh: (bool) $this->option('fresh'),
            importFaqs: $importFaqs,
            importTutorials: $importTutorials,
            copyVideos: (bool) $this->option('copy-videos'),
            dryRun: false,
        );

        $this->info('Legacy help-center import complete.');
        $this->line('FAQs imported: '.$stats['faqs']);
        $this->line('Tutorials imported: '.$stats['tutorials']);

        if ($this->option('copy-videos')) {
            $this->line('Tutorial videos copied: '.$stats['videos_copied']);
        }

        return self::SUCCESS;
    }
}
