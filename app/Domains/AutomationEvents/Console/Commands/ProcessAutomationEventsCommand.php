<?php

declare(strict_types=1);

namespace App\Domains\AutomationEvents\Console\Commands;

use App\Domains\AutomationEvents\Services\AutomationEventService;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAutomationEventsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'automation-events:process-due {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process due automation events for each tenant.';

    public function handle(AutomationEventService $service): int
    {
        $processed = 0;

        $this->foreachTenant(function () use ($service, &$processed): void {
            foreach ($service->dueEvents() as $event) {
                Log::info('ProcessAutomationEventsCommand: processing event', [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                ]);

                $service->markProcessed($event);
                $processed++;
            }
        });

        $this->info("Processed {$processed} automation event(s).");

        return self::SUCCESS;
    }
}
