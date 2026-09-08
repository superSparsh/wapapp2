<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Jobs\DeleteTemplateJob;
use App\Models\Template;
use Illuminate\Console\Command;

class DeleteSoftDeletedTemplates extends Command
{
    protected $signature = 'templates:delete-soft-deleted';
    protected $description = 'Process soft-deleted templates (delete from WhatsApp API then force-delete).';

    public function handle(): int
    {
        $templates = Template::query()
            ->onlyTrashed()
            ->whereNotNull('code')
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No soft-deleted templates with a WhatsApp code to process.');

            // Force-delete templates without a code
            Template::query()->onlyTrashed()->whereNull('code')->forceDelete();

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($templates as $template) {
            DeleteTemplateJob::dispatch($template->id);
            $count++;
        }

        $this->info("Dispatched {$count} template deletion job(s).");

        return self::SUCCESS;
    }
}
