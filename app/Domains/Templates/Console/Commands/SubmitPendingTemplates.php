<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Jobs\SubmitTemplateJob;
use App\Models\Template;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class SubmitPendingTemplates extends Command
{
    use IteratesTenants;

    protected $signature = 'templates:submit-pending {--tenants=* : Tenant IDs to process}';

    protected $description = 'Submit pending-review templates to the WhatsApp API (async via queue).';

    public function handle(): int
    {
        $count = 0;

        $this->foreachTenant(function () use (&$count): void {
            // Only templates that have never been successfully handed to CAMS.
            // (synced_at is set on successful create/modify — do not re-submit while Meta is auditing.)
            $templates = Template::query()
                ->where('status', TemplateStatus::PendingReview)
                ->whereNull('synced_at')
                ->orderBy('updated_at')
                ->limit(50)
                ->get();

            foreach ($templates as $template) {
                // Clear fake local-name codes so Create is used, not Modify.
                if (filled($template->code) && ! filled($template->whatsappCode())) {
                    $template->forceFill(['code' => null])->saveQuietly();
                }

                $isEdit = filled($template->whatsappCode());
                SubmitTemplateJob::dispatch($template->id, $isEdit);
                $count++;
            }
        });

        if ($count === 0) {
            $this->info('No pending templates to submit.');

            return self::SUCCESS;
        }

        $this->info("Dispatched {$count} template submission job(s).");

        return self::SUCCESS;
    }
}
