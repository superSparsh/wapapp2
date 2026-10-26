<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class TittuGatewayStatusService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array{
     *     url: string,
     *     up: bool,
     *     latency_ms: int|null,
     *     checked_at: string,
     *     health_error: string|null,
     *     token_configured: bool,
     *     token_source: string,
     *     detail_error: string|null,
     *     summary: array<string, int|string>|null,
     *     providers: list<array<string, mixed>>
     * }
     */
    public function snapshot(): array
    {
        $base = rtrim((string) config('services.tittu_gateway.url', 'https://gateway.tittu.in'), '/');
        $health = $this->getJson($base.'/health');
        $body = $health['json'];
        $up = $health['ok'] && is_array($body) && ($body['status'] ?? null) === 'ok';

        $token = $this->token();
        $summary = null;
        $providers = [];
        $detailError = null;

        if ($token['value'] === '') {
            $detailError = 'Add a gateway status token to see which providers are active.';
        } else {
            $status = $this->getJson($base.'/admin/status', $token['value']);
            if (! $status['ok'] || ! is_array($status['json'])) {
                $detailError = $status['status_code'] === 401
                    ? 'Status token was rejected.'
                    : ($status['error'] ?? 'Provider status could not be loaded.');
            } else {
                $mapped = $this->mapStatus($status['json']);
                $summary = $mapped['summary'];
                $providers = $mapped['providers'];
            }
        }

        return [
            'url' => $base,
            'up' => $up,
            'latency_ms' => $health['latency_ms'],
            'checked_at' => now()->timezone(config('app.timezone'))->format('d M Y, H:i:s'),
            'health_error' => $up ? null : ($health['error'] ?? 'Gateway health check failed.'),
            'token_configured' => $token['value'] !== '',
            'token_source' => $token['source'],
            'detail_error' => $detailError,
            'summary' => $summary,
            'providers' => $providers,
        ];
    }

    /**
     * @return array{value: string, source: string}
     */
    private function token(): array
    {
        $saved = trim((string) $this->settings->get('tittu_gateway.status_token', ''));
        if ($saved !== '') {
            return ['value' => $saved, 'source' => 'admin'];
        }

        $env = trim((string) config('services.tittu_gateway.status_token', ''));
        if ($env !== '') {
            return ['value' => $env, 'source' => 'env'];
        }

        return ['value' => '', 'source' => 'none'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{summary: array<string, int|string>, providers: list<array<string, mixed>>}
     */
    private function mapStatus(array $payload): array
    {
        $rows = is_array($payload['providers'] ?? null) ? $payload['providers'] : [];
        $providers = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $enabled = (bool) ($row['enabled'] ?? false);
            $hasKey = (bool) ($row['hasKey'] ?? false);
            $cooling = (bool) ($row['isCoolingDown'] ?? false);
            $state = match (true) {
                ! $enabled => 'disabled',
                ! $hasKey => 'key_missing',
                $cooling => 'cooling',
                default => 'active',
            };

            $accounts = is_array($row['credentials'] ?? null) ? $row['credentials'] : [];
            $accountsReady = 0;
            foreach ($accounts as $account) {
                if (is_array($account) && ! empty($account['enabled']) && ! empty($account['hasKey'])) {
                    $accountsReady++;
                }
            }

            $models = [];
            foreach (is_array($row['models'] ?? null) ? $row['models'] : [] as $model) {
                if (is_string($model) && $model !== '') {
                    $models[] = $model;
                }
            }

            $providers[] = [
                'id' => (string) ($row['id'] ?? ''),
                'priority' => (int) ($row['priority'] ?? 0),
                'adapter' => (string) ($row['adapter'] ?? ''),
                'state' => $state,
                'requests_today' => (int) ($row['requestsToday'] ?? 0),
                'requests_minute' => (int) ($row['requestsThisMinute'] ?? 0),
                'limit_rpm' => isset($row['limitRpm']) ? (int) $row['limitRpm'] : null,
                'limit_rpd' => isset($row['limitRpd']) ? (int) $row['limitRpd'] : null,
                'cooldown_seconds' => (int) ($row['cooldownRemainingSeconds'] ?? 0),
                'cooldown_reason' => (string) ($row['cooldownReason'] ?? ''),
                'accounts_ready' => $accountsReady,
                'accounts_total' => count($accounts),
                'models' => $models,
            ];
        }

        usort($providers, fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);

        $active = count(array_filter($providers, fn (array $provider): bool => $provider['state'] === 'active'));
        $coolingCount = count(array_filter($providers, fn (array $provider): bool => $provider['state'] === 'cooling'));

        return [
            'summary' => [
                'status' => (string) ($payload['status'] ?? 'unknown'),
                'active_keys' => (int) ($payload['activeClientKeysCount'] ?? 0),
                'providers_total' => count($providers),
                'providers_active' => $active,
                'providers_cooling' => $coolingCount,
                'requests_today' => array_sum(array_column($providers, 'requests_today')),
                'requests_minute' => array_sum(array_column($providers, 'requests_minute')),
            ],
            'providers' => $providers,
        ];
    }

    /**
     * @return array{ok: bool, status_code: int|null, latency_ms: int, json: array<string, mixed>|null, error: string|null}
     */
    private function getJson(string $url, ?string $token = null): array
    {
        $started = microtime(true);

        try {
            $pending = Http::acceptJson()->connectTimeout(5)->timeout(8);
            if ($token !== null && $token !== '') {
                $pending = $pending->withToken($token);
            }

            $response = $pending->get($url);
            $json = $response->json();

            return [
                'ok' => $response->successful(),
                'status_code' => $response->status(),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'json' => is_array($json) ? $json : null,
                'error' => $response->successful() ? null : 'Gateway returned HTTP '.$response->status().'.',
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'status_code' => null,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'json' => null,
                'error' => 'Gateway did not respond.',
            ];
        }
    }
}
