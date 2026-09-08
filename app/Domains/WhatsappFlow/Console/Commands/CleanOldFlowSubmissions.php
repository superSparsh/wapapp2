<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Console\Commands;

use App\Models\Tenant;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Console\Command;

class CleanOldFlowSubmissions extends Command
{
    protected $signature = 'whatsapp-flows:clean-submissions {--days= : Override retention days from config} {--tenant= : Clean a single tenant id}';

    protected $description = 'Delete flow submissions older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('whatsapp-flows.submission_retention_days', 90));
        $tenantId = $this->option('tenant');
        $cutoff = now()->subDays($days);

        $tenants = $tenantId
            ? Tenant::query()->where('id', $tenantId)->get()
            : Tenant::query()->get();

        $totalDeleted = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $deleted = WhatsappFlowSubmission::query()
                ->where('created_at', '<', $cutoff)
                ->delete();

            if ($deleted > 0) {
                $this->line("Tenant {$tenant->id}: deleted {$deleted} old submission(s).");
            }

            $totalDeleted += $deleted;

            tenancy()->end();
        }

        $this->info("Finished. Deleted {$totalDeleted} submission(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
