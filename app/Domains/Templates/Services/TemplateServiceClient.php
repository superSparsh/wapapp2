<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Inbox\Support\InboxActor;
use App\Domains\Templates\Contracts\TemplateServiceClientInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TemplateServiceClient implements TemplateServiceClientInterface
{
    public function isHealthy(): bool
    {
        try {
            $response = $this->buildRequest()->get($this->baseUrl() . '/health');

            return $response->successful() && ($response->json('status') === 'healthy');
        } catch (\Throwable $e) {
            Log::warning('Template microservice health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listTemplates(array $params = []): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/templates', $params);

        return $this->handleResponse($response, 'listTemplates');
    }

    public function getTemplate(string $uuid): ?array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/templates/' . $uuid);

        if ($response->status() === 404) {
            return null;
        }

        return $this->handleResponse($response, 'getTemplate');
    }

    public function getOptions(): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/templates/options');

        $data = $this->handleResponse($response, 'getOptions');

        return $data['items'] ?? [];
    }

    public function preview(string $code): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/templates/preview/' . $code);

        return $this->handleResponse($response, 'preview');
    }

    public function createDraft(): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/templates/draft');

        return $this->handleResponse($response, 'createDraft');
    }

    public function createFromSetup(array $data): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/templates/from-setup', $data);

        return $this->handleResponse($response, 'createFromSetup');
    }

    public function saveStep(string $uuid, string $step, array $stepData): array
    {
        $response = $this->buildRequest()->put($this->baseUrl() . "/templates/{$uuid}/step/{$step}", [
            'step_data' => $stepData,
        ]);

        return $this->handleResponse($response, 'saveStep');
    }

    public function submit(string $uuid): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . "/templates/{$uuid}/submit");

        return $this->handleResponse($response, 'submit');
    }

    public function deleteTemplate(string $uuid): bool
    {
        $response = $this->buildRequest()->delete($this->baseUrl() . '/templates/' . $uuid);

        return $response->successful();
    }

    public function bulkDelete(array $uuids): int
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/templates/bulk-destroy', [
            'uuids' => $uuids,
        ]);

        $data = $this->handleResponse($response, 'bulkDelete');

        return (int) ($data['deleted'] ?? 0);
    }

    public function listVariables(array $params = []): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/variables', $params);

        return $this->handleResponse($response, 'listVariables');
    }

    public function allVariables(): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/variables/all');

        return $this->handleResponse($response, 'allVariables');
    }

    public function createVariable(array $data): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/variables', $data);

        return $this->handleResponse($response, 'createVariable');
    }

    public function getVariable(string $uuid): ?array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/variables/' . $uuid);

        if ($response->status() === 404) {
            return null;
        }

        return $this->handleResponse($response, 'getVariable');
    }

    public function updateVariable(string $uuid, array $data): array
    {
        $response = $this->buildRequest()->put($this->baseUrl() . '/variables/' . $uuid, $data);

        return $this->handleResponse($response, 'updateVariable');
    }

    public function deleteVariable(string $uuid): bool
    {
        $response = $this->buildRequest()->delete($this->baseUrl() . '/variables/' . $uuid);

        return $response->successful();
    }

    private function baseUrl(): string
    {
        return (string) config('template-service.base_url', 'http://127.0.0.1:8003/api/v1');
    }

    private function buildRequest(): PendingRequest
    {
        $timeout = (int) config('template-service.timeout_seconds', 5);
        $retries = (int) config('template-service.retry_attempts', 2);
        $backoff = (int) config('template-service.retry_backoff_ms', 100);

        return Http::withHeaders($this->requestHeaders())
            ->timeout($timeout)
            ->retry($retries, $backoff, throw: false);
    }

    private function requestHeaders(): array
    {
        $tenantId = tenancy()->initialized ? (string) tenant('id') : (string) session('current_tenant_id', '');
        $token = (string) config('template-service.token', '');

        $headers = [
            'Accept' => 'application/json',
            'X-Service-Token' => $token,
            'X-Tenant-Id' => $tenantId,
            'X-Correlation-Id' => (string) request()->header('X-Correlation-Id', Str::uuid()->toString()),
            'X-Actor-Is-Team-Member' => InboxActor::teamMember() !== null ? '1' : '0',
        ];

        $userId = InboxActor::userId();
        if ($userId !== null) {
            $headers['X-Actor-User-Id'] = (string) $userId;
        }

        $teamMemberId = InboxActor::teamMemberId();
        if ($teamMemberId !== null) {
            $headers['X-Actor-Team-Member-Id'] = (string) $teamMemberId;
        }

        $user = InboxActor::user();
        if ($user !== null && isset($user->uuid)) {
            $headers['X-Actor-User-Uuid'] = (string) $user->uuid;
        }

        $teamMember = InboxActor::teamMember();
        if ($teamMember !== null && isset($teamMember->uuid)) {
            $headers['X-Actor-Team-Member-Uuid'] = (string) $teamMember->uuid;
        }

        return $headers;
    }

    private function handleResponse(Response $response, string $operation): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        Log::error("TemplateServiceClient: {$operation} failed with status {$response->status()}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException(
            "Template microservice returned HTTP {$response->status()}: " . $response->body(),
            $response->status()
        );
    }
}
