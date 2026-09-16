<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Jobs\SubmitTemplateJob;
use App\Models\Template;
use Illuminate\Console\Command;

class SubmitPendingTemplates extends Command
{
    protected $signature = 'templates:submit-pending';

    protected $description = 'Submit pending-review templates to the WhatsApp API (async via queue).';

    public function handle(): int
    {
        // Only templates that have never been successfully handed to CAMS.
        // (synced_at is set on successful create/modify — do not re-submit while Meta is auditing.)
        $templates = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->whereNull('synced_at')
            ->orderBy('updated_at')
            ->limit(50)
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No pending templates to submit.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($templates as $template) {
            $isEdit = filled($template->whatsappCode());
            SubmitTemplateJob::dispatch($template->id, $isEdit);
            $count++;
        }

        $this->info("Dispatched {$count} template submission job(s).");

        return self::SUCCESS;
    }
}
