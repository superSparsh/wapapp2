<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Console\Commands;

use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneInboundWebhookEventsCommand extends Command
{
    protected $signature = 'webhooks:prune-inbound
                            {--status=* : Statuses to delete (default: duplicate,processed)}
                            {--older-than=7 : Delete rows older than N days (ignored when --older-than-hours is set)}
                            {--older-than-hours= : Delete rows older than N hours (preferred for short windows)}
                            {--event-type= : Optional message|status filter}
                            {--tenant-null : Only rows where tenant_id IS NULL}
                            {--received-too : Also prune very old received (use carefully)}
                            {--all-statuses : Delete every status older than cutoff (keep recent window only)}
                            {--limit=5000 : Max delete per run}
                            {--force : Skip confirmation prompts (for cron / night jobs)}
                            {--dry-run : Show counts without deleting}';

    protected $description = 'Delete old inbound webhook audit rows (duplicate/processed; optional ancient received)';

    public function handle(): int
    {
        $hoursOption = $this->option('older-than-hours');
        if ($hoursOption !== null && $hoursOption !== '') {
            $hours = max(1, (int) $hoursOption);
            $cutoff = Carbon::now()->subHours($hours);
            $windowLabel = "{$hours} hour(s)";
        } else {
            $days = max(1, (int) $this->option('older-than'));
            $cutoff = Carbon::now()->subDays($days);
            $windowLabel = "{$days} day(s)";
        }
        $limit = max(1, (int) $this->option('limit'));

        $statuses = array_values(array_filter(array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            (array) $this->option('status'),
        )));

        $allStatuses = (bool) $this->option('all-statuses');

        if ($allStatuses) {
            $statuses = [
                InboundWebhookStatus::Received->value,
                InboundWebhookStatus::Processing->value,
                InboundWebhookStatus::Processed->value,
                InboundWebhookStatus::Failed->value,
                InboundWebhookStatus::Duplicate->value,
            ];
        } elseif ($statuses === []) {
            $statuses = [
                InboundWebhookStatus::Duplicate->value,
                InboundWebhookStatus::Processed->value,
            ];
        }

        if ((bool) $this->option('received-too') && ! in_array(InboundWebhookStatus::Received->value, $statuses, true)) {
            $statuses[] = InboundWebhookStatus::Received->value;
        }

        $query = InboundWebhookEvent::query()
            ->whereIn('status', $statuses)
            ->where('created_at', '<', $cutoff);

        $eventType = strtolower(trim((string) $this->option('event-type')));
        if ($eventType !== '') {
            if (! in_array($eventType, ['message', 'status'], true)) {
                $this->error('--event-type must be message or status');

                return self::FAILURE;
            }
            $query->where('event_type', $eventType);
        }

        if ((bool) $this->option('tenant-null')) {
            $query->whereNull('tenant_id');
        }

        $matched = (clone $query)->count();
        $this->info("Matched {$matched} row(s) older than {$windowLabel} (before {$cutoff->toDateTimeString()}).");
        $this->line('Statuses: '.implode(', ', $statuses));

        if ($matched === 0) {
            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $sample = (clone $query)->orderBy('id')->limit(5)->get(['id', 'event_type', 'status', 'tenant_id', 'created_at']);
            foreach ($sample as $row) {
                $this->line("#{$row->id} {$row->event_type->value} {$row->status->value} tenant=".($row->tenant_id ?? 'null')." {$row->created_at}");
            }

            return self::SUCCESS;
        }

        $needsConfirm = in_array(InboundWebhookStatus::Received->value, $statuses, true)
            || $allStatuses;

        if ($needsConfirm && ! (bool) $this->option('force')
            && ! $this->confirm("This will DELETE up to {$limit} rows (statuses: ".implode(', ', $statuses).'). Continue?', false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $ids = (clone $query)->orderBy('id')->limit($limit)->pluck('id');
        $deleted = InboundWebhookEvent::query()->whereIn('id', $ids)->delete();

        $this->info("Deleted {$deleted} row(s).");
        if ($matched > $deleted) {
            $this->comment('More rows remain — run again.');
        }

        return self::SUCCESS;
    }
}
