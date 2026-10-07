<?php

declare(strict_types=1);

namespace App\Domains\Templates\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\OptInTemplateService;
use App\Models\Template;
use App\Models\WhatsappLine;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

/**
 * Repair rejected / broken opt_in_message templates with $(full_name) and resubmit to CAMS.
 */
class ResubmitFailedOptInTemplatesCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'templates:resubmit-failed-opt-in
                            {--tenants=* : Tenant IDs to process (default: all)}
                            {--dry-run : List matches without repairing / submitting}
                            {--include-wrong-variable : Also fix non-rejected rows missing $(full_name)}';

    protected $description = 'Repair failed opt_in_message templates with $(full_name) and resubmit for each tenant';

    public function handle(OptInTemplateService $optIn): int
    {
        if ($this->skipForMaintenance('templates_sync', 'Templates:')) {
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $includeWrongVariable = (bool) $this->option('include-wrong-variable');
        $repaired = 0;
        $skipped = 0;
        $failed = 0;

        $this->foreachTenant(function ($tenant) use (
            $optIn,
            $dryRun,
            $includeWrongVariable,
            &$repaired,
            &$skipped,
            &$failed,
        ): void {
            $query = Template::query()
                ->where('name', OptInTemplateService::TEMPLATE_NAME)
                ->where(function ($q) use ($includeWrongVariable): void {
                    $q->where('status', TemplateStatus::Rejected)
                        ->orWhere(function ($inner): void {
                            $inner->where('status', TemplateStatus::PendingReview)
                                ->whereNull('synced_at');
                        });

                    if ($includeWrongVariable) {
                        $q->orWhere(function ($inner): void {
                            $inner->where('status', '!=', TemplateStatus::Approved)
                                ->where(function ($body): void {
                                    $body->whereNull('payload->body->text')
                                        ->orWhere('payload->body->text', 'not like', '%$(full_name)%');
                                });
                        });
                    }
                });

            $targets = $query->orderByDesc('id')->get();

            if ($targets->isEmpty()) {
                $skipped++;

                return;
            }

            foreach ($targets as $template) {
                $body = (string) data_get($template->payload, 'body.text', '');
                $this->line(sprintf(
                    '[%s] template#%d status=%s body_has_full_name=%s reason=%s',
                    $tenant->id,
                    $template->id,
                    $template->status instanceof TemplateStatus
                        ? $template->status->value
                        : (string) $template->status,
                    str_contains($body, '$(full_name)') ? 'yes' : 'no',
                    str_replace(["\n", "\r"], ' ', (string) ($template->rejection_reason ?? '')),
                ));
            }

            if ($dryRun) {
                $repaired += $targets->count();

                return;
            }

            $line = WhatsappLine::query()
                ->where('is_default', true)
                ->orderByDesc('id')
                ->first()
                ?? WhatsappLine::query()->orderByDesc('id')->first();

            try {
                $result = $optIn->ensureTemplate($line, forceResubmit: true);
                $result->refresh();
                $this->info(sprintf(
                    '[%s] repaired+resubmit template#%d → status=%s code=%s has=$(full_name)? %s',
                    $tenant->id,
                    $result->id,
                    $result->status instanceof TemplateStatus ? $result->status->value : (string) $result->status,
                    $result->code ?? 'null',
                    str_contains((string) data_get($result->payload, 'body.text'), '$(full_name)') ? 'yes' : 'no',
                ));
                $repaired++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf('[%s] failed: %s', $tenant->id, $e->getMessage()));
            }
        });

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run matched' : 'Repaired/resubmitted')." {$repaired} tenant(s); skipped {$skipped}; errors {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
