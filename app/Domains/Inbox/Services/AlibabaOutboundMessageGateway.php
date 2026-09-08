<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class AlibabaOutboundMessageGateway implements \App\Domains\Inbox\Contracts\OutboundMessageGateway
{
    public function __construct(
        private readonly AlibabaCamsClient $client,
        private readonly CamsOutboundPayloadBuilder $payloadBuilder,
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    public function send(Message $message): void
    {
        if ($message->status === MessageStatus::Sent) {
            return;
        }

        $message->loadMissing(['conversation.whatsappLine']);

        $conversation = $message->conversation;
        $line = $conversation?->whatsappLine;

        if ($conversation === null || $line === null) {
            $this->markFailed($message, 'Conversation or WhatsApp line is missing.');

            return;
        }

        try {
            $payload = $this->payloadBuilder->build($message, $conversation, $line);

            Log::info('CAMS outbound send attempt', [
                'message_id' => $message->id,
                'action' => 'SendChatappMessage',
                'TemplateCode' => $payload['TemplateCode'] ?? null,
                'Language' => $payload['Language'] ?? null,
                'From' => $payload['From'] ?? null,
                'To' => $payload['To'] ?? null,
                'CustSpaceId' => $payload['CustSpaceId'] ?? null,
                'Type' => $payload['Type'] ?? null,
            ]);

            $response = $this->client->sendChatappMessage($payload);

            if (! $response->successful()) {
                $reason = $this->extractCamsError($response->body());
                $debug = $this->payloadDebugSuffix($payload);

                Log::warning('CAMS outbound send rejected', [
                    'message_id' => $message->id,
                    'reason' => $reason,
                    'payload' => Arr::only($payload, ['TemplateCode', 'Language', 'From', 'To', 'CustSpaceId', 'Type']),
                    'response' => $response->body(),
                ]);

                $this->markFailed($message, $reason.$debug);

                return;
            }

            $body = $response->json();
            $externalId = (string) Arr::get($body, 'MessageId', Arr::get($body, 'messageId', ''));

            $message->forceFill([
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
                'external_message_id' => $externalId !== '' ? $externalId : ('local_'.$message->uuid),
                'failed_reason' => null,
            ])->save();

            if ($externalId !== '' && tenancy()->initialized) {
                $tenantId = tenant('id');

                if (is_string($tenantId) && $tenantId !== '') {
                    $this->registryService->indexMessage($tenantId, $externalId, (int) $message->id);
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Outbound CAMS send failed', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $this->markFailed($message, $exception->getMessage());

            throw $exception;
        }
    }

    private function markFailed(Message $message, string $reason): void
    {
        $message->forceFill([
            'status' => MessageStatus::Failed,
            'failed_at' => now(),
            'failed_reason' => $reason,
        ])->save();
    }

    private function extractCamsError(string $body): string
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $message = Arr::get($decoded, 'Message')
                ?? Arr::get($decoded, 'message')
                ?? Arr::get($decoded, 'Error.Message')
                ?? Arr::get($decoded, 'error.message');

            if (is_string($message) && trim($message) !== '') {
                $code = Arr::get($decoded, 'Code') ?? Arr::get($decoded, 'code');

                return is_string($code) && $code !== ''
                    ? trim($message).' ('.$code.')'
                    : trim($message);
            }
        }

        $trimmed = trim($body);

        return $trimmed !== ''
            ? 'WhatsApp provider rejected the message: '.$trimmed
            : 'WhatsApp provider rejected the message.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadDebugSuffix(array $payload): string
    {
        return sprintf(
            ' [debug TemplateCode=%s Language=%s From=%s CustSpaceId=%s]',
            (string) ($payload['TemplateCode'] ?? ''),
            (string) ($payload['Language'] ?? ''),
            (string) ($payload['From'] ?? ''),
            (string) ($payload['CustSpaceId'] ?? ''),
        );
    }
}
