<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Jobs;

use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Renews Google Calendar push notification watches before they expire.
 * Scheduled to run daily. Skips if expiry is > 24 hours away.
 *
 * Optimization: guard clause prevents unnecessary API calls.
 */
class RenewGoogleCalendarWatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;
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
            Log::error('RenewGoogleCalendarWatchJob: tenant not found', ['tenant_id' => $this->tenantId]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $integration = GoogleCalendarIntegration::query()->find($this->integrationId);

            if (! $integration || ! $integration->isEnabled() || ! $integration->hasRefreshToken()) {
                return;
            }

            // Skip if watch does not expire within 24 hours
            $expiration = $integration->settings['watch_expiration'] ?? null;
            if ($expiration && Carbon::parse($expiration)->isAfter(now()->addDay())) {
                return;
            }

            $api = new GoogleCalendarApiService();
            $api->registerWatch($integration);
        } finally {
            tenancy()->end();
        }
    }
}
