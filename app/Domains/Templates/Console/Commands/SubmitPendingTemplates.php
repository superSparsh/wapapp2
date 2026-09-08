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
        $templates = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->whereNull('synced_at')
            ->orWhere(function ($query) {
                $query->where('status', TemplateStatus::PendingReview)
                    ->whereNotNull('code')
                    ->where('synced_at', '<', now()->subMinutes(5));
            })
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No pending templates to submit.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($templates as $template) {
            $isEdit = filled($template->code);
            SubmitTemplateJob::dispatch($template->id, $isEdit);
            $count++;
        }

        $this->info("Dispatched {$count} template submission job(s).");

        return self::SUCCESS;
    }
}
