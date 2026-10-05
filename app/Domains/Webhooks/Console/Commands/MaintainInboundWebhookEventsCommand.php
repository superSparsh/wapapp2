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
                            {--keep-days= : Days of inbound_webhook_events to retain}
                            {--replay-batch= : Stuck rows to dispatch per batch}
                            {--replay-max= : Max stuck rows to dispatch in this run}
                            {--prune-batch= : Rows to delete per prune batch}
                            {--skip-replay : Only prune}
                            {--skip-prune : Only replay stuck}
                            {--dry-run : Report actions without changing data}';

    protected $description = 'Replay recent stuck inbound status webhooks, then prune older rows';

    public function handle(): int
    {
        $keepDays = max(1, (int) ($this->option('keep-days') ?: config('webhooks.inbound_maintenance.keep_days', 2)));
        $replayBatch = max(1, (int) ($this->option('replay-batch') ?: config('webhooks.inbound_maintenance.replay_batch', 500)));
        $replayMax = max(1, (int) ($this->option('replay-max') ?: config('webhooks.inbound_maintenance.replay_max', 10000)));
        $pruneBatch = max(1, (int) ($this->option('prune-batch') ?: config('webhooks.inbound_maintenance.prune_batch', 5000)));
        $dryRun = (bool) $this->option('dry-run');
        $since = Carbon::now()->subDays($keepDays);

        $this->info("Inbound maintain: keep_days={$keepDays} since={$since->toDateTimeString()} dry_run=".($dryRun ? 'yes' : 'no'));

        if (! (bool) $this->option('skip-replay')) {
            $this->replayStuckStatus($since, $replayBatch, $replayMax, $dryRun);
        }

        if (! (bool) $this->option('skip-prune')) {
            $this->pruneOlderThan($keepDays, $pruneBatch, $dryRun);
        }

        $this->info('Inbound maintain finished.');

        return self::SUCCESS;
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

    private function pruneOlderThan(int $keepDays, int $batch, bool $dryRun): void
    {
        $args = [
            '--all-statuses' => true,
            '--older-than' => $keepDays,
            '--limit' => $batch,
            '--force' => true,
        ];

        if ($dryRun) {
            $args['--dry-run'] = true;
            Artisan::call('webhooks:prune-inbound', $args);
            $this->output->write(Artisan::output());

            return;
        }

        $rounds = 0;
        $deletedTotal = 0;
        $maxRounds = (int) config('webhooks.inbound_maintenance.prune_max_rounds', 50);

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

        $this->info("Prune removed {$deletedTotal} row(s) across {$rounds} round(s).");
    }
}
