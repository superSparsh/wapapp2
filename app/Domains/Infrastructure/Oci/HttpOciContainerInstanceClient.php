<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Creates / deletes OCI Container Instances via the regional REST API.
 */
final class HttpOciContainerInstanceClient implements OciContainerInstanceClient
{
    public function isConfigured(): bool
    {
        $cfg = config('oci-workers.ephemeral', []);

        return filled($cfg['tenancy_ocid'] ?? null)
            && filled($cfg['user_ocid'] ?? null)
            && filled($cfg['fingerprint'] ?? null)
            && filled($cfg['private_key'] ?? null)
            && filled($cfg['compartment_id'] ?? null)
            && filled($cfg['availability_domain'] ?? null)
            && filled($cfg['subnet_id'] ?? null)
            && filled($cfg['image_url'] ?? null);
    }

    public function createCampaignWorker(string $displayName, array $environment = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OCI ephemeral HTTP driver is not fully configured.');
        }

        $cfg = config('oci-workers.ephemeral');
        $region = (string) $cfg['region'];
        $host = "containerinstances.{$region}.oci.oraclecloud.com";
        $path = '/20210415/containerInstances';

        $envPairs = [];
        foreach ($environment as $key => $value) {
            $envPairs[] = ['name' => (string) $key, 'value' => (string) $value];
        }

        $payload = [
            'displayName' => $displayName,
            'compartmentId' => $cfg['compartment_id'],
            'availabilityDomain' => $cfg['availability_domain'],
            'shape' => $cfg['shape'],
            'shapeConfig' => [
                'ocpus' => (float) $cfg['ocpus'],
                'memoryInGBs' => (float) $cfg['memory_in_gbs'],
            ],
            'containers' => [[
                'displayName' => $displayName.'-horizon',
                'imageUrl' => $cfg['image_url'],
                'environmentVariables' => $envPairs,
            ]],
            'vnics' => [[
                'subnetId' => $cfg['subnet_id'],
                'isPublicIpAssigned' => (bool) $cfg['assign_public_ip'],
                'displayName' => $displayName.'-vnic',
            ]],
            'containerRestartPolicy' => $cfg['container_restart_policy'] ?? 'ALWAYS',
            'freeformTags' => [
                'app' => 'wapapp',
                'role' => 'campaign-worker',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signer = $this->signer();
        $headers = $signer->sign('POST', $host, $path, $body);

        $response = Http::withHeaders($headers)
            ->withBody($body, 'application/json')
            ->timeout(60)
            ->post("https://{$host}{$path}");

        if (! $response->successful()) {
            Log::error('OCI ephemeral: create Container Instance failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'display_name' => $displayName,
            ]);

            throw new RuntimeException('OCI create Container Instance failed: HTTP '.$response->status().' '.$response->body());
        }

        $ocid = (string) ($response->json('id') ?? '');
        if ($ocid === '') {
            throw new RuntimeException('OCI create Container Instance returned no OCID.');
        }

        Log::info('OCI ephemeral: created campaign worker', [
            'ocid' => $ocid,
            'display_name' => $displayName,
        ]);

        return [
            'ocid' => $ocid,
            'display_name' => $displayName,
        ];
    }

    public function delete(string $ocid): void
    {
        if ($ocid === '' || ! $this->isConfigured()) {
            return;
        }

        $region = (string) config('oci-workers.ephemeral.region');
        $host = "containerinstances.{$region}.oci.oraclecloud.com";
        $path = '/20210415/containerInstances/'.rawurlencode($ocid);

        $signer = $this->signer();
        $headers = $signer->sign('DELETE', $host, $path, '');

        $response = Http::withHeaders($headers)
            ->timeout(60)
            ->delete("https://{$host}{$path}");

        // 404 / 409 = already gone or terminating — treat as success
        if ($response->successful() || in_array($response->status(), [404, 409], true)) {
            Log::info('OCI ephemeral: deleted campaign worker', [
                'ocid' => $ocid,
                'status' => $response->status(),
            ]);

            return;
        }

        Log::error('OCI delete Container Instance failed', [
            'ocid' => $ocid,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException('OCI delete Container Instance failed: HTTP '.$response->status());
    }

    private function signer(): OciRequestSigner
    {
        $cfg = config('oci-workers.ephemeral');
        $key = (string) ($cfg['private_key'] ?? '');

        if ($key !== '' && is_file($key)) {
            if (! is_readable($key)) {
                throw new RuntimeException('OCI private key file is not readable by PHP: '.$key);
            }

            $contents = file_get_contents($key);
            $key = $contents !== false ? $contents : '';
        } elseif ($key !== '' && str_contains($key, 'BEGIN') === false) {
            // Looks like a filesystem path that does not exist / is not visible to this process.
            throw new RuntimeException(
                'OCI private key path is not a readable file (check path + permissions for the Horizon user): '.$key
            );
        }

        $key = str_replace('\\n', "\n", $key);

        if ($key === '' || ! str_contains($key, 'BEGIN')) {
            throw new RuntimeException('OCI private key PEM is empty or invalid. Set OCI_PRIVATE_KEY_PATH to a readable .pem file.');
        }

        return new OciRequestSigner(
            tenancyOcid: (string) $cfg['tenancy_ocid'],
            userOcid: (string) $cfg['user_ocid'],
            fingerprint: (string) $cfg['fingerprint'],
            privateKeyPem: $key,
            passphrase: (string) ($cfg['passphrase'] ?? ''),
        );
    }
}
