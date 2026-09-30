<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci\Jobs;

use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clears OCI active-campaign refs and schedules teardown on the main app.
 *
 * Campaign completion often runs inside the ephemeral CI (OCI_WORKERS_ENABLED=false,
 * possibly stale image). Hopping to the provisioning queue keeps Redis + destroy
 * logic on the host that has current code and OCI credentials.
 */
class ReleaseOciCampaignWorkerRefJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $campaignId,
    ) {}

    public function handle(CampaignOciWorkerLifecycle $lifecycle): void
    {
        try {
            $lifecycle->releaseCampaignRef($this->tenantId, $this->campaignId);
        } catch (Throwable $e) {
            Log::error('ReleaseOciCampaignWorkerRefJob failed', [
                'tenant_id' => $this->tenantId,
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
