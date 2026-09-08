<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Jobs\RenewGoogleCalendarWatchJob;
use App\Domains\ThirdParty\Jobs\SyncCalendlyEventsJob;
use App\Domains\ThirdParty\Jobs\SyncGoogleCalendarEventsJob;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class ScheduleIntegrationSyncCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'operations:schedule-integration-sync {--tenants=* : Tenant IDs to process}';

    protected $description = 'Dispatch integration sync jobs for active Calendly and Google Calendar integrations.';

    public function handle(): int
    {
        $dispatched = 0;

        $this->foreachTenant(function ($tenant) use (&$dispatched): void {
            $tenantId = (string) $tenant->id;

            CalendlyIntegration::query()
                ->where('status', IntegrationStatus::Enabled)
                ->each(function (CalendlyIntegration $integration) use ($tenantId, &$dispatched): void {
                    SyncCalendlyEventsJob::dispatch($integration->id, $tenantId);
                    $dispatched++;
                });

            GoogleCalendarIntegration::query()
                ->where('status', IntegrationStatus::Enabled)
                ->each(function (GoogleCalendarIntegration $integration) use ($tenantId, &$dispatched): void {
                    SyncGoogleCalendarEventsJob::dispatch($integration->id, $tenantId);
                    RenewGoogleCalendarWatchJob::dispatch($integration->id, $tenantId);
                    $dispatched += 2;
                });
        });

        $this->info("Dispatched {$dispatched} integration sync job(s).");

        return self::SUCCESS;
    }
}
