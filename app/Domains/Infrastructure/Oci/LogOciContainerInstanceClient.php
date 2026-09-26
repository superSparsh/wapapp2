<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dry-run / test driver — no OCI API calls.
 */
final class LogOciContainerInstanceClient implements OciContainerInstanceClient
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function createCampaignWorker(string $displayName, array $environment = []): array
    {
        $ocid = 'ocid1.containerinstance.oc1.test.'.Str::lower(Str::random(24));

        Log::info('OCI ephemeral: [log driver] create campaign worker', [
            'ocid' => $ocid,
            'display_name' => $displayName,
            'env_keys' => array_keys($environment),
        ]);

        return [
            'ocid' => $ocid,
            'display_name' => $displayName,
        ];
    }

    public function delete(string $ocid): void
    {
        Log::info('OCI ephemeral: [log driver] delete campaign worker', [
            'ocid' => $ocid,
        ]);
    }
}
