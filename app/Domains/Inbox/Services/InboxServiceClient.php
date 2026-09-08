<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Contracts\InboxServiceClientInterface;
use App\Domains\Inbox\Support\InboxActor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxServiceClient implements InboxServiceClientInterface
{
    public function __construct(
        private readonly InboxSettingsService $settingsService,
        private readonly InboxAccessService $accessService,
    ) {}

    public function isHealthy(): bool
    {
        try {
            $response = $this->buildRequest()->get($this->baseUrl().'/health');

            return $response->successful() && ($response->json('status') === 'healthy');
        } catch (\Throwable $e) {
            Log::warning('Inbox microservice health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getThreads(int $lineId, array $filters = []): array
    {
        $params = array_merge(['line_id' => $lineId], $filters);
        $response = $this->buildRequest()->get($this->baseUrl().'/threads', $params);

        return $this->handleResponse($response, 'getThreads');
    }

    public function getUnreadCount(?int $lineId = null): int
    {
        $params = $lineId !== null ? ['line_id' => $lineId] : [];
        $response = $this->buildRequest()->get($this->baseUrl().'/unread-count', $params);

        return (int) $this->handleResponse($response, 'getUnreadCount')['unread_total'] ?? 0;
    }

    public function getMessages(string $conversationUuid, array $filters = []): array
    {
        $response = $this->buildRequest()->get(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/messages',
            $filters
        );

        return $this->handleResponse($response, 'getMessages');
    }

    public function sendMessage(string $conversationUuid, string $body): array
    {
        $response = $this->buildRequest()->post(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/messages',
            ['body' => $body]
        );

        return $this->handleResponse($response, 'sendMessage');
    }

    public function sendMedia(
        string $conversationUuid,
        UploadedFile $file,
        string $mediaType,
        ?string $caption = null,
    ): array {
        $request = $this->buildRequest()->attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        );

        $response = $request->post($this->baseUrl().'/conversations/'.$conversationUuid.'/media', [
            'media_type' => $mediaType,
            'caption' => $caption,
        ]);

        return $this->handleResponse($response, 'sendMedia');
    }

    public function sendTemplate(
        string $conversationUuid,
        string $templateCode,
        array $templateParams = [],
        ?string $language = null,
    ): array {
        $response = $this->buildRequest()->post(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/templates',
            [
                'template_code' => $templateCode,
                'template_params' => $templateParams,
                'language' => $language,
            ]
        );

        return $this->handleResponse($response, 'sendTemplate');
    }

    public function sendLocation(string $conversationUuid, float $latitude, float $longitude): array
    {
        $response = $this->buildRequest()->post(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/location',
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]
        );

        return $this->handleResponse($response, 'sendLocation');
    }

    public function sendSticker(string $conversationUuid, UploadedFile $file): array
    {
        $request = $this->buildRequest()->attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        );

        $response = $request->post($this->baseUrl().'/conversations/'.$conversationUuid.'/sticker');

        return $this->handleResponse($response, 'sendSticker');
    }

    public function markRead(string $conversationUuid): bool
    {
        $response = $this->buildRequest()->post(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/read'
        );

        return (bool) ($this->handleResponse($response, 'markRead')['ok'] ?? false);
    }

    public function markAllRead(int $lineId, array $filters = []): int
    {
        $params = array_merge(['line_id' => $lineId], $filters);
        $response = $this->buildRequest()->post($this->baseUrl().'/mark-all-read', $params);

        return (int) ($this->handleResponse($response, 'markAllRead')['updated'] ?? 0);
    }

    public function assign(
        string $conversationUuid,
        ?string $assigneeKey = null,
        ?int $userId = null,
        ?int $teamMemberId = null,
    ): array {
        $response = $this->buildRequest()->post(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/assign',
            [
                'assignee' => $assigneeKey,
                'user_id' => $userId,
                'team_member_id' => $teamMemberId,
            ]
        );

        return $this->handleResponse($response, 'assign');
    }

    public function toggleResponseType(string $conversationUuid, bool $aiEnabled): bool
    {
        $response = $this->buildRequest()->post(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/response-type',
            ['ai_enabled' => $aiEnabled]
        );

        return (bool) ($this->handleResponse($response, 'toggleResponseType')['ai_enabled'] ?? false);
    }

    public function toggleAllResponseType(int $lineId, bool $aiEnabled, array $filters = []): int
    {
        $params = array_merge([
            'line_id' => $lineId,
            'ai_enabled' => $aiEnabled,
        ], $filters);

        $response = $this->buildRequest()->post($this->baseUrl().'/response-type/all', $params);

        return (int) ($this->handleResponse($response, 'toggleAllResponseType')['updated'] ?? 0);
    }

    public function getWindowStatus(string $conversationUuid): array
    {
        $response = $this->buildRequest()->get(
            $this->baseUrl().'/conversations/'.$conversationUuid.'/window'
        );

        return $this->handleResponse($response, 'getWindowStatus');
    }

    public function storeContact(
        int $lineId,
        string $name,
        string $phone,
        ?string $linePhone = null,
        string $responseType = 'human_response',
        ?int $contactId = null,
    ): array {
        $response = $this->buildRequest()->post($this->baseUrl().'/contacts', [
            'line_id' => $lineId,
            'line_phone' => $linePhone,
            'name' => $name,
            'phone' => $phone,
            'response_type' => $responseType,
            'contact_id' => $contactId,
        ]);

        return $this->handleResponse($response, 'storeContact');
    }

    public function recordInbound(
        int $lineId,
        string $contactPhone,
        string $body,
        ?string $contactName = null,
        ?string $externalMessageId = null,
        string $messageType = 'text',
        ?string $linePhone = null,
        ?int $contactId = null,
        ?array $metadata = null,
    ): array {
        $response = $this->buildRequest()->post($this->baseUrl().'/inbound', [
            'line_id' => $lineId,
            'line_phone' => $linePhone,
            'contact_phone' => $contactPhone,
            'contact_name' => $contactName,
            'contact_id' => $contactId,
            'body' => $body,
            'external_message_id' => $externalMessageId,
            'message_type' => $messageType,
            'metadata' => $metadata,
        ]);

        return $this->handleResponse($response, 'recordInbound');
    }

    public function updateDeliveryStatus(
        string $externalMessageId,
        string $status,
        ?string $failedReason = null,
    ): bool {
        $response = $this->buildRequest()->post($this->baseUrl().'/delivery-status', [
            'external_message_id' => $externalMessageId,
            'status' => $status,
            'failed_reason' => $failedReason,
        ]);

        return (bool) ($this->handleResponse($response, 'updateDeliveryStatus')['ok'] ?? false);
    }

    public function exportConversation(string $conversationUuid): StreamedResponse
    {
        $url = $this->baseUrl().'/conversations/'.$conversationUuid.'/export';
        $headers = $this->requestHeaders();

        return response()->streamDownload(function () use ($url, $headers): void {
            $stream = fopen('php://output', 'wb');
            $response = Http::withHeaders($headers)
                ->timeout(config('inbox-service.timeout_seconds', 30))
                ->get($url);

            if ($stream !== false) {
                fwrite($stream, $response->body());
                fclose($stream);
            }
        }, 'chat-export-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportAll(int $lineId, array $filters = []): StreamedResponse
    {
        $url = $this->baseUrl().'/export';
        $params = array_merge(['line_id' => $lineId], $filters);
        $headers = $this->requestHeaders();

        return response()->streamDownload(function () use ($url, $params, $headers): void {
            $stream = fopen('php://output', 'wb');
            $response = Http::withHeaders($headers)
                ->timeout(config('inbox-service.timeout_seconds', 60))
                ->get($url, $params);

            if ($stream !== false) {
                fwrite($stream, $response->body());
                fclose($stream);
            }
        }, 'inbox-export-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function baseUrl(): string
    {
        return (string) config('inbox-service.base_url', 'http://127.0.0.1:8001/api/v1');
    }

    private function buildRequest(): PendingRequest
    {
        $timeout = (int) config('inbox-service.timeout_seconds', 5);
        $retryAttempts = (int) config('inbox-service.retry_attempts', 2);
        $retryBackoff = (int) config('inbox-service.retry_backoff_ms', 100);

        return Http::withHeaders($this->requestHeaders())
            ->timeout($timeout)
            ->retry($retryAttempts, $retryBackoff, throw: false);
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        $tenantId = tenancy()->initialized ? (string) tenant('id') : '';
        $token = (string) config('inbox-service.token', '');

        $headers = [
            'Accept' => 'application/json',
            'X-Service-Token' => $token,
            'X-Tenant-Id' => $tenantId,
            'X-Correlation-Id' => (string) Str::uuid(),
            'X-Actor-Phone-Masking' => $this->settingsService->isPhoneMaskingEnabled() ? 'true' : 'false',
            'X-Actor-Is-Team-Member' => $this->accessService->isTeamMember() ? 'true' : 'false',
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
        if ($user !== null) {
            $headers['X-Actor-User-Uuid'] = (string) $user->uuid;
        }

        $teamMember = InboxActor::teamMember();
        if ($teamMember !== null) {
            $headers['X-Actor-Team-Member-Uuid'] = (string) $teamMember->uuid;
        }

        $assignedLines = $this->accessService->assignedLineIds();
        if (! empty($assignedLines)) {
            $headers['X-Actor-Assigned-Lines'] = implode(',', $assignedLines);
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleResponse(Response $response, string $action): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        Log::error("Inbox microservice {$action} error", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException(
            "Inbox service error ({$response->status()}): ".($response->json('message') ?? $response->body())
        );
    }
}
