<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Handlers;

use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\MessageStatus;
use App\Models\CampaignRecipient;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;

class DeliveryStatusHandler
{
    public function __construct(
        private readonly AlibabaWebhookParser $parser,
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    public function handle(InboundWebhookEvent $event): void
    {
        $items = $this->parser->parsePayload($event->payload);
        $item = $this->parser->firstItem($items);

        if ($item === null) {
            throw new \RuntimeException('Status payload is empty.');
        }

        $messageId = (string) ($item['MessageId'] ?? $item['messageId'] ?? '');
        $status = (string) ($item['Status'] ?? $item['status'] ?? '');

        if ($messageId === '' || $status === '') {
            throw new \RuntimeException('Status payload is missing MessageId or Status.');
        }

        $tenant = $this->registryService->resolveTenantByExternalMessageId($messageId);

        if ($tenant === null) {
            $groupId = (string) ($item['GroupId'] ?? $item['groupId'] ?? $item['TaskId'] ?? '');
            if ($groupId !== '') {
                $tenant = $this->registryService->resolveTenantByExternalMessageId($groupId);
            }
        }

        if ($tenant === null) {
            $from = (string) ($item['From'] ?? $item['from'] ?? '');
            $resolved = $this->registryService->resolveByBusinessPhone($from);
            $tenant = $resolved['tenant'] ?? null;
        }

        if ($tenant === null) {
            throw new \RuntimeException('No tenant index found for message '.$messageId);
        }

        tenancy()->initialize($tenant);

        try {
            $message = Message::query()->where('external_message_id', $messageId)->first();
            $now = now();

            if ($message !== null) {
                $updates = $this->messageUpdates($status, $message, $item, $now);
                if ($updates !== []) {
                    $message->forceFill($updates)->save();
                }
            }

            $this->syncCampaignRecipient($item, $messageId, $status, $now);

            if (config('inbox-service.enabled')) {
                try {
                    app(\App\Domains\Inbox\Contracts\InboxServiceClientInterface::class)->updateDeliveryStatus(
                        externalMessageId: $messageId,
                        status: strtolower($status),
                        failedReason: (string) ($item['ErrorDescription'] ?? null),
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed forwarding delivery status to inbox microservice', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $event->forceFill([
                'tenant_id' => $tenant->id,
            ])->save();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function messageUpdates(string $status, Message $message, array $item, \Illuminate\Support\Carbon $now): array
    {
        return match ($status) {
            'Sent' => [
                'status' => MessageStatus::Sent,
                'sent_at' => $message->sent_at ?? $now,
            ],
            'Delivered' => [
                'status' => MessageStatus::Delivered,
                'delivered_at' => $message->delivered_at ?? $now,
            ],
            'Read' => [
                'status' => MessageStatus::Read,
                'read_at' => $message->read_at ?? $now,
            ],
            'Failed' => [
                'status' => MessageStatus::Failed,
                'failed_at' => $message->failed_at ?? $now,
                'failed_reason' => (string) ($item['ErrorDescription'] ?? 'Delivery failed'),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function syncCampaignRecipient(array $item, string $messageId, string $status, \Illuminate\Support\Carbon $now): void
    {
        $recipientStatus = match ($status) {
            'Sent' => CampaignRecipientStatus::Sent,
            'Delivered' => CampaignRecipientStatus::Delivered,
            'Read' => CampaignRecipientStatus::Read,
            'Failed' => CampaignRecipientStatus::Failed,
            default => null,
        };

        if ($recipientStatus === null) {
            return;
        }

        $recipient = $this->findCampaignRecipient($item, $messageId);

        if ($recipient === null) {
            Log::warning('Campaign recipient not found for Alibaba status webhook', [
                'message_id' => $messageId,
                'status' => $status,
                'to' => $item['To'] ?? $item['to'] ?? null,
                'group_id' => $item['GroupId'] ?? $item['groupId'] ?? $item['TaskId'] ?? null,
            ]);

            return;
        }

        $updates = [
            'status' => $recipientStatus,
            'message_id' => $messageId,
        ];

        if ($recipientStatus === CampaignRecipientStatus::Sent) {
            $updates['sent_at'] = $recipient->sent_at ?? $now;
        }
        if ($recipientStatus === CampaignRecipientStatus::Delivered) {
            $updates['delivered_at'] = $recipient->delivered_at ?? $now;
            $updates['sent_at'] = $recipient->sent_at ?? $now;
        }
        if ($recipientStatus === CampaignRecipientStatus::Read) {
            $updates['read_at'] = $recipient->read_at ?? $now;
            $updates['delivered_at'] = $recipient->delivered_at ?? $now;
            if ($recipient->status !== CampaignRecipientStatus::Read) {
                $recipient->campaign?->increment('total_read');
            }
        }
        if ($recipientStatus === CampaignRecipientStatus::Failed) {
            $updates['failed_at'] = $recipient->failed_at ?? $now;
            $updates['failure_reason'] = mb_substr((string) ($item['ErrorDescription'] ?? 'Delivery failed'), 0, 255);
            if ($recipient->status !== CampaignRecipientStatus::Failed) {
                $recipient->campaign?->increment('total_failed');
            }
        }

        $recipient->update($updates);
    }

    /**
     * Resolve campaign recipient for an Alibaba status callback.
     *
     * Campaign sends previously stored local messages.id when outbound was async;
     * status webhooks only carry the provider MessageId. Match both, then GroupId+To,
     * then phone + sent_at near the inbox message.
     *
     * @param  array<string, mixed>  $item
     */
    private function findCampaignRecipient(array $item, string $messageId): ?CampaignRecipient
    {
        $recipient = CampaignRecipient::query()->where('message_id', $messageId)->first();
        if ($recipient !== null) {
            return $recipient;
        }

        $message = Message::query()->where('external_message_id', $messageId)->first();
        if ($message !== null) {
            $recipient = CampaignRecipient::query()
                ->where('message_id', (string) $message->id)
                ->first();
            if ($recipient !== null) {
                return $recipient;
            }

            $to = PhoneNormalizer::normalize((string) ($item['To'] ?? $item['to'] ?? ''));
            if ($to !== null && $message->sent_at !== null) {
                $variants = PhoneNormalizer::lookupVariants($to);
                $recipient = CampaignRecipient::query()
                    ->whereIn('contact_phone', $variants)
                    ->whereIn('status', [
                        CampaignRecipientStatus::Sent,
                        CampaignRecipientStatus::Delivered,
                        CampaignRecipientStatus::Read,
                    ])
                    ->whereBetween('sent_at', [
                        $message->sent_at->copy()->subMinutes(10),
                        $message->sent_at->copy()->addMinutes(10),
                    ])
                    ->orderByDesc('id')
                    ->first();
                if ($recipient !== null) {
                    return $recipient;
                }
            }
        }

        $groupId = (string) ($item['GroupId'] ?? $item['groupId'] ?? $item['TaskId'] ?? '');
        $to = PhoneNormalizer::normalize((string) ($item['To'] ?? $item['to'] ?? ''));
        if ($groupId === '' || $to === null) {
            return null;
        }

        $variants = PhoneNormalizer::lookupVariants($to);

        return CampaignRecipient::query()
            ->where('message_id', $groupId)
            ->where(function ($query) use ($variants): void {
                $query->whereIn('contact_phone', $variants);
            })
            ->first();
    }
}
