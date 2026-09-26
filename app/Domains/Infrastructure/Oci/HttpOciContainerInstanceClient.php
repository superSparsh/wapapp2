<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
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

        // OCI API expects a string map, not [{name,value}, ...].
        $envMap = [];
        foreach ($environment as $key => $value) {
            $envMap[(string) $key] = (string) $value;
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
                'displayName' => 'horizon',
                'imageUrl' => $cfg['image_url'],
                'environmentVariables' => $envMap === [] ? new \stdClass : $envMap,
                'command' => ['php'],
                'arguments' => ['artisan', 'horizon'],
                'workingDirectory' => '/var/www/html',
            ]],
            'vnics' => [[
                'subnetId' => $cfg['subnet_id'],
                'isPublicIpAssigned' => (bool) $cfg['assign_public_ip'],
                'displayName' => 'vnic',
            ]],
            'containerRestartPolicy' => $cfg['container_restart_policy'] ?? 'ALWAYS',
            'freeformTags' => [
                'app' => 'wapapp',
                'role' => 'campaign-worker',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->signedRequest('POST', $host, $path, $body);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            Log::error('OCI ephemeral: create Container Instance failed', [
                'status' => $response['status'],
                'body' => $this->truncateBody($response['body']),
                'display_name' => $displayName,
                'host' => $host,
            ]);

            throw new RuntimeException(
                'OCI create Container Instance failed: HTTP '.$response['status'].' '.$this->truncateBody($response['body'])
            );
        }

        /** @var array<string, mixed> $json */
        $json = json_decode($response['body'], true) ?? [];
        $ocid = (string) ($json['id'] ?? '');
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

        $response = $this->signedRequest('DELETE', $host, $path, '');

        if (
            ($response['status'] >= 200 && $response['status'] < 300)
            || in_array($response['status'], [404, 409], true)
        ) {
            Log::info('OCI ephemeral: deleted campaign worker', [
                'ocid' => $ocid,
                'status' => $response['status'],
            ]);

            return;
        }

        Log::error('OCI ephemeral: delete Container Instance failed', [
            'ocid' => $ocid,
            'status' => $response['status'],
            'body' => $this->truncateBody($response['body']),
        ]);

        throw new RuntimeException('OCI delete Container Instance failed: HTTP '.$response['status']);
    }

    /**
     * Use cURL so signed Content-Length / body bytes are not mutated by Guzzle.
     *
     * @return array{status: int, body: string}
     */
    private function signedRequest(string $method, string $host, string $path, string $body): array
    {
        $signer = $this->signer();
        $signed = $signer->sign($method, $host, $path, $body);

        $headers = [];
        foreach ($signed as $name => $value) {
            $headers[] = $name.': '.$value;
        }

        $ch = curl_init("https://{$host}{$path}");
        if ($ch === false) {
            throw new RuntimeException('Unable to init cURL for OCI request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HEADER => false,
        ]);

        if (strtoupper($method) !== 'GET' && strtoupper($method) !== 'HEAD') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('OCI HTTP request failed: '.$error);
        }

        return [
            'status' => $status,
            'body' => (string) $raw,
        ];
    }

    private function truncateBody(string $body): string
    {
        $flat = preg_replace('/\s+/', ' ', trim(strip_tags($body))) ?? trim($body);

        return mb_substr($flat, 0, 1500);
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
