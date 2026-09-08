<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Contracts\OutboundTemplateSenderInterface;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpInboxTemplateSender implements OutboundTemplateSenderInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function sendTemplate(
        int $whatsappLineId,
        string $contactPhone,
        string $templateCode,
        array $templateParams = [],
        ?string $language = 'en_US',
    ): array {
        $baseUrl = rtrim((string) config('campaigns.inbox_service.url', 'http://127.0.0.1:8001/api/v1'), '/');
        $token = (string) config('campaigns.inbox_service.token', '');
        $timeout = (int) config('campaigns.inbox_service.timeout', 5);

        try {
            // Forward request to inbox-service
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-Service-Token' => $token,
                    'X-Tenant-Id' => (string) $this->tenantContext->getTenantId(),
                    'X-Actor-User-Id' => (string) ($this->tenantContext->getUserId() ?? ''),
                    'X-Actor-Team-Member-Id' => (string) ($this->tenantContext->getTeamMemberId() ?? ''),
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/conversations/by-phone/template", [
                    'whatsapp_line_id' => $whatsappLineId,
                    'contact_phone' => $contactPhone,
                    'template_code' => $templateCode,
                    'template_params' => $templateParams,
                    'language' => $language ?? 'en_US',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $msgId = $data['message']['external_message_id'] ?? $data['message']['uuid'] ?? (string) ($data['message']['id'] ?? '');

                return [
                    'success' => true,
                    'message_id' => $msgId,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'message_id' => null,
                'error' => $response->body(),
            ];
        } catch (Throwable $e) {
            Log::warning('Outbound template send failed via HTTP inbox service', [
                'error' => $e->getMessage(),
                'phone' => $contactPhone,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
