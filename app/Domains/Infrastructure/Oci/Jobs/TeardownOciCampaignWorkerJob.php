<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci\Jobs;

use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeardownOciCampaignWorkerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(
        CampaignOciWorkerLifecycle $lifecycle,
        OciContainerInstanceClient $client,
    ): void {
        try {
            $lifecycle->teardownWorker($client);
        } catch (Throwable $e) {
            Log::error('TeardownOciCampaignWorkerJob failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
