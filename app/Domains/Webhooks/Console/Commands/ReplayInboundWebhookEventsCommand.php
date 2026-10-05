<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Console\Commands;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Console\Command;

class ReplayInboundWebhookEventsCommand extends Command
{
    protected $signature = 'webhooks:replay-inbound
                            {--id=* : Replay specific inbound_webhook_events.id values}
                            {--message-id= : Replay all status events for this Alibaba MessageId}
                            {--sync : Process synchronously instead of queueing}';

    protected $description = 'Re-process stuck inbound Alibaba webhooks (received / failed / duplicate)';

    public function handle(): int
    {
        $ids = array_filter(array_map('intval', (array) $this->option('id')));
        $messageId = trim((string) $this->option('message-id'));

        $query = InboundWebhookEvent::query()
            ->whereIn('status', [
                InboundWebhookStatus::Received->value,
                InboundWebhookStatus::Failed->value,
                InboundWebhookStatus::Duplicate->value,
            ]);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($messageId !== '') {
            $query->where('idempotency_key', 'like', $messageId.'%');
        } else {
            $this->error('Pass --id= or --message-id=');

            return self::FAILURE;
        }

        $events = $query->orderBy('id')->get();

        if ($events->isEmpty()) {
            $this->warn('No matching stuck webhook events.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $count = 0;

        foreach ($events as $event) {
            $event->forceFill([
                'status' => InboundWebhookStatus::Received,
                'error_message' => null,
            ])->save();

            if ($sync) {
                dispatch_sync(new ProcessInboundWebhookJob((int) $event->id));
            } else {
                ProcessInboundWebhookJob::dispatch((int) $event->id)
                    ->onQueue(\App\Support\OciWorkload::queueForInboundEvent($event->event_type));
            }

            $count++;
            $this->line("Queued replay for event #{$event->id} ({$event->idempotency_key})");
        }

        $this->info("Replay dispatched for {$count} event(s).");

        return self::SUCCESS;
    }
}
