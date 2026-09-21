<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for the Python FastAPI + Chroma AI service.
 * Knowledge Base content is never stored in MySQL — this client is the only write path.
 */
class AiPythonClient
{
    public function isConfigured(): bool
    {
        return (bool) config('ai.enabled', true)
            && filled(config('ai.python_url'));
    }

    public function baseUrl(): string
    {
        return (string) config('ai.python_url');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->http()->get($this->url($path), $query);

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $json = [], array $query = []): array
    {
        $response = $this->http()->asJson()->post($this->url($path, $query), $json);

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function postMultipart(string $path, UploadedFile $file, array $query = [], array $fields = []): array
    {
        $pending = $this->http()->attach(
            'file',
            file_get_contents($file->getRealPath()) ?: '',
            $file->getClientOriginalName(),
        );

        foreach ($fields as $key => $value) {
            if ($value === null) {
                continue;
            }
            $pending = $pending->attach((string) $key, is_scalar($value) ? (string) $value : json_encode($value));
        }

        $response = $pending->post($this->url($path, $query));

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        $response = $this->http()->delete($this->url($path, $query));

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $path, array $query = []): string
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Python AI service is not configured (PYTHON_AI_URL).');
        }

        return Http::timeout((int) config('ai.timeout', 90))
            ->connectTimeout((int) config('ai.connect_timeout', 10))
            ->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $json = ['raw' => $response->body()];
        }

        if (! $response->successful()) {
            $message = (string) ($json['error'] ?? $json['detail'] ?? $json['message'] ?? $response->body());

            throw new RuntimeException(
                $message !== '' ? $message : 'AI service request failed (HTTP '.$response->status().').',
                $response->status()
            );
        }

        return $json;
    }
}
