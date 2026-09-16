<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Jobs\DeleteTemplateJob;
use App\Models\Template;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class DeleteSoftDeletedTemplates extends Command
{
    use IteratesTenants;

    protected $signature = 'templates:delete-soft-deleted {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process soft-deleted templates (delete from WhatsApp API then force-delete).';

    public function handle(): int
    {
        $dispatched = 0;
        $forceDeleted = 0;

        $this->foreachTenant(function () use (&$dispatched, &$forceDeleted): void {
            $templates = Template::query()
                ->onlyTrashed()
                ->orderBy('deleted_at')
                ->get();

            foreach ($templates as $template) {
                if (filled($template->whatsappCode())) {
                    DeleteTemplateJob::dispatch($template->id);
                    $dispatched++;

                    continue;
                }

                $template->forceDelete();
                $forceDeleted++;
            }
        });

        $this->info("Dispatched {$dispatched} WhatsApp deletion job(s); force-deleted {$forceDeleted} local-only template(s).");

        return self::SUCCESS;
    }
}
