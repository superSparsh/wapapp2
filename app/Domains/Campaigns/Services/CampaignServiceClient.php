<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Campaigns\Contracts\CampaignServiceClientInterface;
use App\Domains\Inbox\Support\InboxActor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignServiceClient implements CampaignServiceClientInterface
{
    public function isHealthy(): bool
    {
        try {
            $response = $this->buildRequest()->get($this->baseUrl() . '/health');

            return $response->successful() && ($response->json('status') === 'healthy');
        } catch (\Throwable $e) {
            Log::warning('Campaign microservice health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listCampaigns(array $params = []): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/campaigns', $params);

        return $this->handleResponse($response, 'listCampaigns');
    }

    public function getCampaign(string $uuid): ?array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/campaigns/' . $uuid);

        if ($response->status() === 404) {
            return null;
        }

        return $this->handleResponse($response, 'getCampaign');
    }

    public function createCampaign(array $data): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns', $data);

        return $this->handleResponse($response, 'createCampaign');
    }

    public function updateCampaign(string $uuid, array $data): array
    {
        $response = $this->buildRequest()->put($this->baseUrl() . '/campaigns/' . $uuid, $data);

        return $this->handleResponse($response, 'updateCampaign');
    }

    public function deleteCampaign(string $uuid): bool
    {
        $response = $this->buildRequest()->delete($this->baseUrl() . '/campaigns/' . $uuid);

        return $response->successful();
    }

    public function launchCampaign(string $uuid): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/launch');

        return $this->handleResponse($response, 'launchCampaign');
    }

    public function toggleCampaign(string $uuid): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/toggle');

        return $this->handleResponse($response, 'toggleCampaign');
    }

    public function duplicateCampaign(string $uuid): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/duplicate');

        return $this->handleResponse($response, 'duplicateCampaign');
    }

    public function sendTestMessage(string $uuid, string $phone, array $variables = []): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/test-message', [
            'phone' => $phone,
            'template_variables' => $variables,
        ]);

        return $this->handleResponse($response, 'sendTestMessage');
    }

    public function resendFailed(string $uuid): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/resend-failed');

        return $this->handleResponse($response, 'resendFailed');
    }

    public function calculateCost(string $uuid, ?string $category = 'MARKETING'): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/campaigns/' . $uuid . '/cost', [
            'category' => $category ?? 'MARKETING',
        ]);

        return $this->handleResponse($response, 'calculateCost');
    }

    public function importRecipients(string $uuid, UploadedFile $file): array
    {
        $request = $this->buildRequest()->attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        );

        $response = $request->post($this->baseUrl() . '/campaigns/' . $uuid . '/import-recipients');

        return $this->handleResponse($response, 'importRecipients');
    }

    public function populateRecipients(string $uuid, array $recipients): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/recipients', [
            'recipients' => $recipients,
        ]);

        return $this->handleResponse($response, 'populateRecipients');
    }

    public function storeWebhook(string $uuid, array $data): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/' . $uuid . '/webhooks', $data);

        return $this->handleResponse($response, 'storeWebhook');
    }

    public function getStatistics(string $uuid): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/campaigns/' . $uuid . '/statistics');

        return $this->handleResponse($response, 'getStatistics');
    }

    public function getRecipients(string $uuid, array $params = []): array
    {
        $response = $this->buildRequest()->get($this->baseUrl() . '/campaigns/' . $uuid . '/recipients', $params);

        return $this->handleResponse($response, 'getRecipients');
    }

    public function exportRecipients(string $uuid): StreamedResponse
    {
        $url = $this->baseUrl() . '/campaigns/' . $uuid . '/export';
        $filename = 'campaign-recipients-' . $uuid . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($url): void {
            $stream = $this->buildRequest()->withOptions(['stream' => true])->get($url);
            $body = $stream->toPsrResponse()->getBody();

            while (! $body->eof()) {
                echo $body->read(1024);
                flush();
            }
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function processDue(): array
    {
        $response = $this->buildRequest()->post($this->baseUrl() . '/campaigns/process-due');

        return $this->handleResponse($response, 'processDue');
    }

    private function buildRequest(): PendingRequest
    {
        $timeout = (int) config('campaign-service.timeout_seconds', 5);
        $retryAttempts = (int) config('campaign-service.retry_attempts', 2);
        $retryBackoff = (int) config('campaign-service.retry_backoff_ms', 100);

        return Http::withHeaders($this->requestHeaders())
            ->timeout($timeout)
            ->retry($retryAttempts, $retryBackoff, throw: false);
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        $tenantId = tenancy()->initialized ? (string) tenant('id') : (string) session('current_tenant_id', '');
        $token = (string) config('campaign-service.token', '');

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

    private function baseUrl(): string
    {
        return rtrim((string) config('campaign-service.base_url', 'http://127.0.0.1:8002/api/v1'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function handleResponse(Response $response, string $action): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        Log::warning("Campaign microservice {$action} call failed", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException("Campaign microservice {$action} failed with status {$response->status()}: {$response->body()}");
    }
}
