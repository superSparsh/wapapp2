<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Console\Commands;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use App\Support\OciWorkload;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ReplayInboundWebhookEventsCommand extends Command
{
    protected $signature = 'webhooks:replay-inbound
                            {--id=* : Replay specific inbound_webhook_events.id values}
                            {--message-id= : Replay all status events for this Alibaba MessageId}
                            {--stuck : Replay stuck rows (received / failed) in bulk}
                            {--event-type= : Limit to message|status}
                            {--status=* : Stuck statuses to include (default: received,failed)}
                            {--since= : Only rows created on/after this datetime}
                            {--until= : Only rows created before this datetime}
                            {--limit=500 : Max rows to replay in this run}
                            {--dry-run : Show count / sample without dispatching}
                            {--sync : Process synchronously instead of queueing}';

    protected $description = 'Re-process stuck inbound Alibaba webhooks (received / failed / duplicate)';

    public function handle(): int
    {
        $ids = array_filter(array_map('intval', (array) $this->option('id')));
        $messageId = trim((string) $this->option('message-id'));
        $stuck = (bool) $this->option('stuck');

        if ($ids === [] && $messageId === '' && ! $stuck) {
            $this->error('Pass --id=, --message-id=, or --stuck');

            return self::FAILURE;
        }

        $query = $this->baseQuery($ids, $messageId, $stuck);

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('No matching stuck webhook events.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $events = (clone $query)->orderBy('id')->limit($limit)->get();

        $this->info("Matched {$total} row(s); processing {$events->count()} (limit={$limit}).");

        if ((bool) $this->option('dry-run')) {
            foreach ($events->take(10) as $event) {
                $this->line("#{$event->id} {$event->event_type->value} {$event->status->value} {$event->idempotency_key} {$event->created_at}");
            }
            if ($events->count() > 10) {
                $this->line('... (sample capped at 10)');
            }

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $ok = 0;
        $fail = 0;

        foreach ($events as $event) {
            try {
                $event->forceFill([
                    'status' => InboundWebhookStatus::Received,
                    'error_message' => null,
                ])->save();

                if ($sync) {
                    dispatch_sync(new ProcessInboundWebhookJob((int) $event->id));
                } else {
                    ProcessInboundWebhookJob::dispatch((int) $event->id)
                        ->onQueue(OciWorkload::queueForInboundEvent($event->event_type));
                }

                $ok++;
                if ($this->output->isVerbose()) {
                    $this->line("Replayed #{$event->id} ({$event->idempotency_key})");
                }
            } catch (\Throwable $exception) {
                $fail++;
                $this->warn("FAIL #{$event->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Replay done: ok={$ok} fail={$fail}.");
        if ($total > $events->count()) {
            $this->comment('More rows remain — run again with the same flags (or raise --limit).');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     */
    private function baseQuery(array $ids, string $messageId, bool $stuck): Builder
    {
        $statuses = array_values(array_filter(array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            (array) $this->option('status'),
        )));

        if ($statuses === []) {
            $statuses = [
                InboundWebhookStatus::Received->value,
                InboundWebhookStatus::Failed->value,
            ];
            if (! $stuck) {
                $statuses[] = InboundWebhookStatus::Duplicate->value;
            }
        }

        $query = InboundWebhookEvent::query()->whereIn('status', $statuses);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($messageId !== '') {
            $query->where('idempotency_key', 'like', $messageId.'%');
        }

        $eventType = strtolower(trim((string) $this->option('event-type')));
        if ($eventType !== '') {
            $type = match ($eventType) {
                'status' => InboundWebhookEventType::Status,
                'message' => InboundWebhookEventType::Message,
                default => null,
            };
            if ($type === null) {
                $this->error('--event-type must be message or status');
                // Force empty result rather than throwing mid-handle awkwardly.
                $query->whereRaw('1 = 0');
            } else {
                $query->where('event_type', $type);
            }
        }

        $since = trim((string) $this->option('since'));
        if ($since !== '') {
            $query->where('created_at', '>=', $since);
        }

        $until = trim((string) $this->option('until'));
        if ($until !== '') {
            $query->where('created_at', '<', $until);
        }

        return $query;
    }
}
