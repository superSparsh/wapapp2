<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Console\Commands;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use App\Support\OciWorkload;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Hands-off inbound webhook hygiene:
 * 1) Re-queue recent stuck status events (so OCI/status workers can catch up)
 * 2) Delete rows older than the keep window (default 2 days)
 */
class MaintainInboundWebhookEventsCommand extends Command
{
    protected $signature = 'webhooks:maintain-inbound
                            {--keep-hours= : Hours of inbound_webhook_events to retain (preferred)}
                            {--keep-days= : Legacy days retain window (converted to hours when keep-hours unset)}
                            {--replay-batch= : Stuck rows to dispatch per batch}
                            {--replay-max= : Max stuck rows to dispatch in this run}
                            {--prune-batch= : Rows to delete per prune batch}
                            {--skip-replay : Only prune}
                            {--skip-prune : Only replay stuck}
                            {--safe-prune : Prune only processed+duplicate (for frequent runs)}
                            {--dry-run : Report actions without changing data}';

    protected $description = 'Replay recent stuck inbound status webhooks, then prune older rows';

    public function handle(): int
    {
        $keepHours = $this->resolveKeepHours();
        $replayBatch = max(1, (int) ($this->option('replay-batch') ?: config('webhooks.inbound_maintenance.replay_batch', 500)));
        $replayMax = max(1, (int) ($this->option('replay-max') ?: config('webhooks.inbound_maintenance.replay_max', 10000)));
        $pruneBatch = max(1, (int) ($this->option('prune-batch') ?: config('webhooks.inbound_maintenance.prune_batch', 5000)));
        $dryRun = (bool) $this->option('dry-run');
        $safePrune = (bool) $this->option('safe-prune');
        $since = Carbon::now()->subHours($keepHours);

        $this->info("Inbound maintain: keep_hours={$keepHours} since={$since->toDateTimeString()} dry_run=".($dryRun ? 'yes' : 'no'));

        if (! (bool) $this->option('skip-replay')) {
            $this->replayStuckStatus($since, $replayBatch, $replayMax, $dryRun);
        }

        // Frequent runs: delete processed/duplicate only. Nightly: every status past keep window.
        if ($safePrune) {
            $this->pruneOlderThan($keepHours, $pruneBatch, $dryRun, allStatuses: false);
        } elseif (! (bool) $this->option('skip-prune')) {
            $this->pruneOlderThan($keepHours, $pruneBatch, $dryRun, allStatuses: true);
        }

        $this->info('Inbound maintain finished.');

        return self::SUCCESS;
    }

    private function resolveKeepHours(): int
    {
        if ($this->option('keep-hours') !== null && $this->option('keep-hours') !== '') {
            return max(1, (int) $this->option('keep-hours'));
        }

        if ($this->option('keep-days') !== null && $this->option('keep-days') !== '') {
            return max(1, (int) $this->option('keep-days') * 24);
        }

        return max(1, (int) config('webhooks.inbound_maintenance.keep_hours', 12));
    }

    private function replayStuckStatus(Carbon $since, int $batch, int $max, bool $dryRun): void
    {
        $base = InboundWebhookEvent::query()
            ->where('event_type', InboundWebhookEventType::Status)
            ->whereIn('status', [
                InboundWebhookStatus::Received->value,
                InboundWebhookStatus::Failed->value,
            ])
            ->where('created_at', '>=', $since);

        // Let the original recorder/queue attempt finish before we re-dispatch.
        $graceMinutes = max(1, (int) config('webhooks.inbound_maintenance.replay_grace_minutes', 10));
        $base->where(function ($q) use ($graceMinutes): void {
            $q->where('status', InboundWebhookStatus::Failed->value)
                ->orWhere(function ($q2) use ($graceMinutes): void {
                    $q2->where('status', InboundWebhookStatus::Received->value)
                        ->where('created_at', '<=', Carbon::now()->subMinutes($graceMinutes));
                });
        });

        $matched = (clone $base)->count();
        $this->info("Stuck status in keep window (grace {$graceMinutes}m): {$matched}");

        if ($matched === 0) {
            return;
        }

        if ($dryRun) {
            $this->comment('Dry-run: would replay up to '.min($matched, $max).' stuck status row(s).');

            return;
        }

        $dispatched = 0;
        $lastId = 0;
        $maxBatches = max(1, (int) config('webhooks.inbound_maintenance.replay_max_batches', 4));
        $batches = 0;

        while ($dispatched < $max && $batches < $maxBatches) {
            $take = min($batch, $max - $dispatched);
            $events = (clone $base)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($take)
                ->get();

            if ($events->isEmpty()) {
                break;
            }

            foreach ($events as $event) {
                $event->forceFill([
                    'status' => InboundWebhookStatus::Received,
                    'error_message' => null,
                ])->save();

                ProcessInboundWebhookJob::dispatch((int) $event->id)
                    ->onQueue(OciWorkload::queueForInboundEvent($event->event_type));

                $dispatched++;
                $lastId = (int) $event->id;
            }

            $batches++;
            $this->line("Replayed batch #{$batches}: total_dispatched={$dispatched}");
        }

        $this->info("Replay dispatched {$dispatched} stuck status job(s).");
    }

    private function pruneOlderThan(int $keepHours, int $batch, bool $dryRun, bool $allStatuses = true): void
    {
        $args = [
            '--older-than-hours' => $keepHours,
            '--limit' => $batch,
            '--force' => true,
        ];

        if ($allStatuses) {
            $args['--all-statuses'] = true;
        } else {
            // Default prune statuses are processed + duplicate (safe for frequent runs).
            $args['--status'] = [
                InboundWebhookStatus::Processed->value,
                InboundWebhookStatus::Duplicate->value,
            ];
        }

        if ($dryRun) {
            $args['--dry-run'] = true;
            Artisan::call('webhooks:prune-inbound', $args);
            $this->output->write(Artisan::output());

            return;
        }

        $rounds = 0;
        $deletedTotal = 0;
        $maxRounds = (int) config('webhooks.inbound_maintenance.prune_max_rounds', 50);
        $mode = $allStatuses ? 'all-statuses' : 'processed+duplicate';

        while ($rounds < $maxRounds) {
            Artisan::call('webhooks:prune-inbound', $args);
            $out = Artisan::output();
            $this->output->write($out);

            if (preg_match('/Deleted\s+(\d+)/i', $out, $m) !== 1) {
                break;
            }

            $deleted = (int) $m[1];
            $deletedTotal += $deleted;
            $rounds++;

            if ($deleted === 0) {
                break;
            }

            usleep(200_000);
        }

        $this->info("Prune ({$mode}) removed {$deletedTotal} row(s) across {$rounds} round(s).");
    }
}
