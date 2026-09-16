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
            ->orderBy('deleted_at')
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No soft-deleted templates to process.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $forceDeleted = 0;

        foreach ($templates as $template) {
            if (filled($template->whatsappCode())) {
                DeleteTemplateJob::dispatch($template->id);
                $dispatched++;

                continue;
            }

            $template->forceDelete();
            $forceDeleted++;
        }

        $this->info("Dispatched {$dispatched} WhatsApp deletion job(s); force-deleted {$forceDeleted} local-only template(s).");

        return self::SUCCESS;
    }
}
