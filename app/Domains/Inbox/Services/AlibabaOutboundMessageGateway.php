<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Domains\WhatsApp\Services\CamsTemplateMediaUploader;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AlibabaOutboundMessageGateway implements \App\Domains\Inbox\Contracts\OutboundMessageGateway
{
    public function __construct(
        private readonly AlibabaCamsClient $client,
        private readonly CamsOutboundPayloadBuilder $payloadBuilder,
        private readonly WhatsappLineRegistryService $registryService,
        private readonly CamsTemplateMediaUploader $mediaUploader,
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
            $this->ensureOutboundMediaIsHosted($message, $line);
            $payload = $this->payloadBuilder->build($message->refresh(), $conversation, $line);

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

    /**
     * CAMS cannot fetch local /storage URLs — upload to Alibaba OSS first.
     */
    private function ensureOutboundMediaIsHosted(Message $message, WhatsappLine $line): void
    {
        if (! in_array($message->message_type, [
            MessageType::Image,
            MessageType::Video,
            MessageType::Audio,
            MessageType::Document,
            MessageType::Sticker,
        ], true)) {
            return;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $mediaUrl = trim((string) ($metadata['media_url'] ?? ''));

        if ($this->mediaUploader->isProviderHostedUrl($mediaUrl)) {
            return;
        }

        $custSpaceId = trim((string) ($line->alibaba_cust_space_id ?? ''));
        if ($custSpaceId === '') {
            throw new RuntimeException('WhatsApp CustSpaceId is missing; cannot upload media for delivery.');
        }

        $mediaPath = trim((string) ($metadata['media_path'] ?? ''));
        $fileName = (string) ($metadata['file_name'] ?? 'media.bin');
        $mime = (string) ($metadata['file_type'] ?? 'application/octet-stream');
        $contents = null;

        if ($mediaPath !== '') {
            $disk = Storage::disk((string) config('whatsapp.media.disk', 'public'));
            if ($disk->exists($mediaPath)) {
                $raw = $disk->get($mediaPath);
                if (is_string($raw) && $raw !== '') {
                    $contents = $raw;
                    $mime = (string) ($disk->mimeType($mediaPath) ?: $mime);
                    $fileName = $fileName !== '' ? $fileName : basename($mediaPath);
                }
            }
        }

        if ($contents === null && preg_match('#^https?://#i', $mediaUrl) === 1) {
            $response = Http::timeout(60)
                ->withHeaders(['User-Agent' => 'wapapp-inbox-media/1.0'])
                ->get($mediaUrl);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to download media for WhatsApp upload (HTTP '.$response->status().').');
            }

            $contents = $response->body();
            $mime = (string) ($response->header('Content-Type') ?: $mime);
            if ($fileName === '' || $fileName === 'media.bin') {
                $fileName = basename((string) (parse_url($mediaUrl, PHP_URL_PATH) ?: 'media.bin'));
            }
        }

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Media file is missing; cannot deliver to WhatsApp.');
        }

        $hostedUrl = $this->mediaUploader->uploadBytes($contents, $fileName, $mime, $custSpaceId);

        $metadata['media_url_local'] = $mediaUrl !== '' ? $mediaUrl : ($metadata['media_url_local'] ?? null);
        $metadata['media_url'] = $hostedUrl;
        $message->forceFill(['metadata' => $metadata])->save();
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
