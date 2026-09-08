<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Jobs;

use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use App\Domains\ThirdParty\Services\GoogleCalendarEventSyncService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendarEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;

    public function __construct(
        private readonly int    $integrationId,
        private readonly string $tenantId,
    ) {}

    public function handle(): void
    {
        /** @var Tenant|null $tenant */
        $tenant = tenancy()->central(fn () => Tenant::query()->find($this->tenantId));

        if (! $tenant) {
            Log::error('SyncGoogleCalendarEventsJob: tenant not found', ['tenant_id' => $this->tenantId]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $integration = GoogleCalendarIntegration::query()->find($this->integrationId);

            if (! $integration || ! $integration->isEnabled()) {
                return;
            }

            $api         = new GoogleCalendarApiService();
            $syncService = new GoogleCalendarEventSyncService($api);
            $syncService->sync($integration);
        } finally {
            tenancy()->end();
        }
    }
}
