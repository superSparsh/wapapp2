<?php

declare(strict_types=1);

namespace App\Domains\Drip\Console\Commands;

use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Domains\Drip\Support\DripTriggerCatalog;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessDripAutomationsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'drip:process-due {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process due drip automations for scheduled trigger types.';

    public function handle(DripTriggerDispatcher $dispatcher): int
    {
        $processed = 0;

        $this->foreachTenant(function () use ($dispatcher, &$processed): void {
            $campaigns = DripCampaign::query()
                ->active()
                ->whereIn('trigger_type', [
                    'specific-date',
                    'weekly-recurring',
                    'monthly-recurring',
                ])
                ->get();

            foreach ($campaigns as $campaign) {
                if (! $campaign->isWithinDateRange() || ! $this->isTriggerDue($campaign)) {
                    continue;
                }

                $contacts = Contact::query()
                    ->when($campaign->audience_id, fn ($query) => $query->where('mail_list_id', $campaign->audience_id))
                    ->limit(500)
                    ->get();

                foreach ($contacts as $contact) {
                    $dispatcher->dispatchForContact((string) $campaign->trigger_type, $contact);
                    $processed++;
                }
            }
        });

        $this->info("Processed {$processed} drip trigger(s).");

        return self::SUCCESS;
    }

    private function isTriggerDue(DripCampaign $campaign): bool
    {
        $options = (array) ($campaign->trigger_options ?? []);
        $timezone = (string) ($campaign->timezone ?? config('app.timezone', 'UTC'));
        $now = now($timezone);

        return match (DripTriggerCatalog::normalizeType((string) $campaign->trigger_type)) {
            'specific-date' => $this->isSpecificDateDue($options, $now),
            'weekly-recurring' => $this->isWeeklyRecurringDue($options, $now),
            'monthly-recurring' => $this->isMonthlyRecurringDue($options, $now),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function isSpecificDateDue(array $options, Carbon $now): bool
    {
        $scheduledAt = $options['scheduled_at'] ?? $options['date'] ?? null;

        if (! is_string($scheduledAt) || $scheduledAt === '') {
            return false;
        }

        $target = Carbon::parse($scheduledAt, $now->timezoneName);

        return $target->isSameMinute($now);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function isWeeklyRecurringDue(array $options, Carbon $now): bool
    {
        $day = strtolower((string) ($options['day_of_week'] ?? $options['weekday'] ?? ''));
        $time = (string) ($options['time'] ?? '09:00');

        if ($day === '' || strtolower($now->englishDayOfWeek) !== $day) {
            return false;
        }

        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $now->hour === (int) $hour && $now->minute === (int) $minute;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function isMonthlyRecurringDue(array $options, Carbon $now): bool
    {
        $dayOfMonth = (int) ($options['day_of_month'] ?? $options['day'] ?? 0);
        $time = (string) ($options['time'] ?? '09:00');

        if ($dayOfMonth < 1 || $now->day !== $dayOfMonth) {
            return false;
        }

        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $now->hour === (int) $hour && $now->minute === (int) $minute;
    }
}
