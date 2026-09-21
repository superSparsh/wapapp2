<?php

declare(strict_types=1);

namespace App\Domains\Drip\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Domains\Drip\Support\DripSchedule;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class ProcessDripAutomationsCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'drip:process-due {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process due drip automations for scheduled trigger types.';

    public function handle(DripTriggerDispatcher $dispatcher, DripSchedule $schedule): int
    {
        if ($this->skipForMaintenance('drip', 'Drip:')) {
            return self::SUCCESS;
        }

        $processed = 0;

        $this->foreachTenant(function () use ($dispatcher, $schedule, &$processed): void {
            $campaigns = DripCampaign::query()
                ->active()
                ->whereIn('trigger_type', [
                    'specific-date',
                    'weekly-recurring',
                    'monthly-recurring',
                    'say-happy-birthday',
                    'subscriber-added-date',
                    'specific-date-time-of-user',
                ])
                ->get();

            foreach ($campaigns as $campaign) {
                if (! $campaign->isWithinDateRange() || ! $campaign->hasFlowData()) {
                    continue;
                }

                $contacts = Contact::query()
                    ->when($campaign->audience_id, fn ($query) => $query->where('mail_list_id', $campaign->audience_id))
                    ->limit(500)
                    ->get();

                foreach ($contacts as $contact) {
                    $key = $schedule->enrollmentKey($campaign, $contact);
                    if ($key === null) {
                        continue;
                    }

                    if ($dispatcher->enroll($campaign, $contact, enrollmentKey: $key)) {
                        $processed++;
                    }
                }
            }
        });

        $this->info("Processed {$processed} drip trigger(s).");

        return self::SUCCESS;
    }
}
