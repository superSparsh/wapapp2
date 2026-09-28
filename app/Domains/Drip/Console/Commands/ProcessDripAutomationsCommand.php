<?php

declare(strict_types=1);

namespace App\Domains\Drip\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Domains\Drip\Support\DripSchedule;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Models\DripCampaignState;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDripAutomationsCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'drip:process-due {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process due drip automations for scheduled trigger types and resume expired waits.';

    public function handle(DripTriggerDispatcher $dispatcher, DripSchedule $schedule): int
    {
        if ($this->skipForMaintenance('drip', 'Drip:')) {
            return self::SUCCESS;
        }

        $processed = 0;
        $resumed = 0;

        $this->foreachTenant(function () use ($dispatcher, $schedule, &$processed, &$resumed): void {
            $resumed += $this->resumeExpiredWaits();

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

        $this->info("Processed {$processed} drip trigger(s); resumed {$resumed} waiting state(s).");

        return self::SUCCESS;
    }

    /**
     * If a delayed ExecuteDripStepJob was lost (queue restart / Redis flush),
     * re-dispatch Waiting states whose expires_at has passed.
     */
    private function resumeExpiredWaits(): int
    {
        $states = DripCampaignState::query()
            ->where('status', ChatbotFlowStateStatus::Waiting)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(200)
            ->get();

        $count = 0;

        foreach ($states as $state) {
            try {
                ExecuteDripStepJob::dispatch($state->id)
                    ->onQueue((string) config('chatbot.drip.queue', 'default'));
                $count++;
            } catch (\Throwable $e) {
                Log::warning('drip.resume_expired_wait_failed', [
                    'state_id' => $state->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
