<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging HTTP v1 client.
 * No-ops when FCM is disabled or credentials are missing/invalid.
 */
class FcmClient
{
    public function isReady(): bool
    {
        if (! (bool) config('fcm.enabled', true)) {
            return false;
        }

        $path = (string) config('fcm.credentials', '');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $json = $this->credentials();

        return is_array($json)
            && filled($json['client_email'] ?? null)
            && filled($json['private_key'] ?? null)
            && filled($this->projectId($json));
    }

    /**
     * @param  array<string, mixed>  $notification
     * @param  array<string, string>  $data
     */
    public function sendToToken(string $token, array $notification, array $data = []): bool
    {
        $token = trim($token);
        if ($token === '' || ! $this->isReady()) {
            return false;
        }

        try {
            $accessToken = $this->accessToken();
            if ($accessToken === null) {
                return false;
            }

            $credentials = $this->credentials() ?? [];
            $projectId = $this->projectId($credentials);
            $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => (string) ($notification['title'] ?? 'New message'),
                        'body' => (string) ($notification['body'] ?? ''),
                    ],
                    'data' => $this->stringifyData($data),
                    'android' => [
                        'priority' => 'HIGH',
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::timeout((int) config('fcm.timeout_seconds', 8))
                ->withToken($accessToken)
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            $body = $response->json();
            $errorCode = (string) data_get($body, 'error.status', '');
            $errorMessage = (string) data_get($body, 'error.message', $response->body());

            // Drop dead tokens so we don't keep retrying.
            if (in_array($errorCode, ['NOT_FOUND', 'INVALID_ARGUMENT'], true)
                || str_contains(strtolower($errorMessage), 'not a valid fcm registration token')
                || str_contains(strtolower($errorMessage), 'requested entity was not found')
            ) {
                Log::notice('FCM token rejected by Firebase; caller should prune', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                ]);

                return false;
            }

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('FCM send exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $path = (string) config('fcm.credentials', '');
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function projectId(array $credentials): string
    {
        $configured = trim((string) config('fcm.project_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        return trim((string) ($credentials['project_id'] ?? ''));
    }

    private function accessToken(): ?string
    {
        $cacheKey = (string) config('fcm.access_token_cache_key', 'fcm.access_token');
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();
        if ($credentials === null) {
            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $jwtClaim = $this->base64UrlEncode(json_encode([
            'iss' => (string) $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $jwtHeader.'.'.$jwtClaim;
        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);
        if ($privateKey === false) {
            Log::warning('FCM credentials private_key could not be parsed');

            return null;
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            Log::warning('FCM JWT signing failed');

            return null;
        }

        $assertion = $unsigned.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout((int) config('fcm.timeout_seconds', 8))
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            Log::warning('FCM OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $accessToken = trim((string) $response->json('access_token'));
        if ($accessToken === '') {
            return null;
        }

        Cache::put(
            $cacheKey,
            $accessToken,
            (int) config('fcm.access_token_ttl_seconds', 3000),
        );

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = (string) $value;
            } else {
                $out[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }
        }

        return $out;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
