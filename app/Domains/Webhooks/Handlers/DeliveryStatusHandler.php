<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Handlers;

use App\Domains\Audience\Services\NonWhatsAppNumberService;
use App\Domains\Audience\Services\OptInMessageService;
use App\Domains\Billing\Services\TemplateWalletChargeService;
use App\Domains\FormBuilder\Services\FormSubmissionService;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\MessageExternalIndex;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;

class DeliveryStatusHandler
{
    public function __construct(
        private readonly AlibabaWebhookParser $parser,
        private readonly WhatsappLineRegistryService $registryService,
        private readonly TemplateWalletChargeService $templateWalletChargeService,
    ) {}

    public function handle(InboundWebhookEvent $event): void
    {
        $items = $this->parser->parsePayload($event->payload);
        $item = $this->parser->firstItem($items);

        if ($item === null) {
            throw new \RuntimeException('Status payload is empty.');
        }

        $messageId = (string) ($item['MessageId'] ?? $item['messageId'] ?? '');
        $rawStatus = (string) ($item['Status'] ?? $item['status'] ?? '');
        $status = match (strtolower(trim($rawStatus))) {
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'read' => 'Read',
            'failed', 'undelivered' => 'Failed',
            default => $rawStatus,
        };

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
            $message = $this->findOutboundMessage($messageId, $item);
            $now = now();

            if ($message === null) {
                Log::warning('Outbound message not found for Alibaba status webhook', [
                    'message_id' => $messageId,
                    'status' => $status,
                    'to' => $item['To'] ?? $item['to'] ?? null,
                    'from' => $item['From'] ?? $item['from'] ?? null,
                ]);
            }

            if ($message !== null) {
                $updates = $this->messageUpdates($status, $message, $item, $now);
                if ($updates !== []) {
                    $message->forceFill($updates)->save();

                    try {
                        app(\App\Domains\Inbox\Services\InboxBroadcastService::class)
                            ->messageStatusUpdated($message->refresh());
                    } catch (\Throwable $e) {
                        Log::warning('Inbox message status broadcast failed', [
                            'message_id' => $message->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->syncOptInContactDelivery($message, $status, $item, $now);
            }

            $this->syncCampaignRecipient($item, $messageId, $status, $now);
            $this->syncFormSubmission(
                $message !== null ? $message->fresh(['conversation']) : null,
                $messageId,
                $status,
                $item,
            );

            // Form sync may have linked outbound_message_id — re-resolve for wallet charge.
            $message ??= $this->findOutboundMessage($messageId, $item);

            if ($message !== null && $message->message_type === MessageType::Template) {
                $recipient = CampaignRecipient::query()->where('message_id', $messageId)->first()
                    ?? CampaignRecipient::query()->where('message_id', (string) $message->id)->first();

                try {
                    $this->templateWalletChargeService->chargeIfDelivered(
                        message: $message->refresh(),
                        deliveryStatus: $status,
                        recipient: $recipient,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Template wallet charge hook failed', [
                        'message_id' => $message->id,
                        'status' => $status,
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
     * Resolve the local outbound message for a provider status callback.
     *
     * @param  array<string, mixed>  $item
     */
    private function findOutboundMessage(string $messageId, array $item): ?Message
    {
        $message = Message::query()->where('external_message_id', $messageId)->first();
        if ($message !== null) {
            return $message;
        }

        $index = MessageExternalIndex::query()
            ->where('external_message_id', $messageId)
            ->first();
        if ($index?->message_id) {
            $message = Message::query()->find((int) $index->message_id);
            if ($message !== null) {
                // Backfill provider id so future lookups are direct.
                if (blank($message->external_message_id) || str_starts_with((string) $message->external_message_id, 'local_')) {
                    $message->forceFill(['external_message_id' => $messageId])->save();
                }

                return $message;
            }
        }

        $byOutbound = FormSubmission::query()
            ->where('external_message_id', $messageId)
            ->whereNotNull('outbound_message_id')
            ->orderByDesc('id')
            ->first();
        if ($byOutbound?->outbound_message_id) {
            $message = Message::query()->find((int) $byOutbound->outbound_message_id);
            if ($message !== null) {
                if (blank($message->external_message_id) || str_starts_with((string) $message->external_message_id, 'local_')) {
                    $message->forceFill(['external_message_id' => $messageId])->save();
                }

                return $message;
            }
        }

        $to = PhoneNormalizer::normalize((string) ($item['To'] ?? $item['to'] ?? ''));
        if ($to === null) {
            return null;
        }

        $variants = PhoneNormalizer::lookupVariants($to);

        return Message::query()
            ->where('direction', 'outbound')
            ->where('message_type', MessageType::Template)
            ->whereHas('conversation', function ($query) use ($variants): void {
                $query->whereIn('contact_phone', $variants);
            })
            ->where(function ($query): void {
                $query->where('metadata->wallet_source', 'form_builder')
                    ->orWhereNotNull('metadata->form_submission_id');
            })
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->first();
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
                'failed_reason' => $this->extractFailureReason($item),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function syncOptInContactDelivery(Message $message, string $status, array $item, \Illuminate\Support\Carbon $now): void
    {
        $meta = is_array($message->metadata) ? $message->metadata : [];
        $contactId = (int) ($meta['opt_in_contact_id'] ?? 0);
        $contact = $contactId > 0
            ? Contact::query()->find($contactId)
            : null;

        if ($contact === null) {
            $message->loadMissing('conversation');
            $phone = PhoneNormalizer::normalize((string) ($item['To'] ?? $item['to'] ?? $message->conversation?->contact_phone ?? ''));
            if ($phone) {
                $contact = Contact::query()
                    ->where('phone', $phone)
                    ->where('send_opt_in_message', 'yes')
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if ($contact === null || ($contact->send_opt_in_message ?? 'no') !== 'yes') {
            return;
        }

        $error = (string) ($item['ErrorDescription'] ?? $item['ErrorCode'] ?? '');
        $errorCode = (string) ($item['ErrorCode'] ?? '');

        if ($status === 'Delivered' || $status === 'Read') {
            $contact->forceFill([
                'opt_in_message_delivery_status' => OptInMessageService::DELIVERY_DELIVERED,
                'opt_in_message_delivered_at' => $contact->opt_in_message_delivered_at ?? $now,
                'opt_in_message_delivery_error' => null,
            ])->save();

            return;
        }

        if ($status === 'Failed') {
            $contact->forceFill([
                'opt_in_message_delivery_status' => OptInMessageService::DELIVERY_FAILED,
                'opt_in_message_delivery_error' => $error !== '' ? $error : 'Delivery failed',
            ])->save();

            $nonWa = app(NonWhatsAppNumberService::class);
            if ($nonWa->isUndeliverableCode($errorCode) || $nonWa->errorLooksLike131026($error)) {
                $nonWa->markContact($contact);
            }
        }
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

        $previousStatus = $recipient->status;
        $alreadyDelivered = in_array($previousStatus, [
            CampaignRecipientStatus::Delivered,
            CampaignRecipientStatus::Read,
            CampaignRecipientStatus::Response,
        ], true);

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
            if (! $alreadyDelivered) {
                $recipient->campaign?->increment('total_delivered');
            }
        }
        if ($recipientStatus === CampaignRecipientStatus::Read) {
            $updates['read_at'] = $recipient->read_at ?? $now;
            $updates['delivered_at'] = $recipient->delivered_at ?? $now;
            if (! $alreadyDelivered) {
                $recipient->campaign?->increment('total_delivered');
            }
            if ($previousStatus !== CampaignRecipientStatus::Read) {
                $recipient->campaign?->increment('total_read');
            }
        }
        if ($recipientStatus === CampaignRecipientStatus::Failed) {
            $updates['failed_at'] = $recipient->failed_at ?? $now;
            $updates['failure_reason'] = $this->extractFailureReason($item);
            if ($recipient->status !== CampaignRecipientStatus::Failed) {
                $recipient->campaign?->increment('total_failed');
            }

            $error = $this->extractFailureReason($item);
            $errorCode = (string) ($item['ErrorCode'] ?? $item['errorCode'] ?? '');
            $nonWa = app(NonWhatsAppNumberService::class);
            if ($nonWa->isUndeliverableCode($errorCode) || $nonWa->errorLooksLike131026($error)) {
                if ($recipient->contact_id) {
                    $contact = Contact::query()->find($recipient->contact_id);
                    if ($contact) {
                        $nonWa->markContact($contact);
                    }
                } else {
                    $nonWa->markByPhone($recipient->contact_phone);
                }
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

    /**
     * Keep form-builder submission delivery stats in sync with WhatsApp status webhooks.
     *
     * @param  array<string, mixed>  $item
     */
    private function syncFormSubmission(?Message $message, string $messageId, string $status, array $item): void
    {
        $formStatus = match ($status) {
            'Sent' => 'sent',
            'Delivered' => 'delivered',
            'Read' => 'read',
            'Failed' => 'failed',
            default => null,
        };

        if ($formStatus === null) {
            return;
        }

        $submission = $this->findFormSubmission($message, $messageId, $item);
        if ($submission === null) {
            return;
        }

        try {
            app(FormSubmissionService::class)->updateMessageStatus(
                submission: $submission,
                status: $formStatus,
                externalId: $messageId,
                failedReason: $formStatus === 'failed' ? $this->extractFailureReason($item) : null,
                outboundMessageId: $message?->id,
            );
        } catch (\Throwable $e) {
            Log::warning('Form submission status sync failed', [
                'message_id' => $messageId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findFormSubmission(?Message $message, string $messageId, array $item): ?FormSubmission
    {
        // 1) Legacy-style: link via local outbound message id (conversations.msg_id parity).
        if ($message !== null) {
            $byOutbound = FormSubmission::query()
                ->where('outbound_message_id', $message->id)
                ->orderByDesc('id')
                ->first();
            if ($byOutbound !== null) {
                return $byOutbound;
            }

            $meta = is_array($message->metadata) ? $message->metadata : [];
            $submissionId = (int) ($meta['form_submission_id'] ?? 0);
            if ($submissionId > 0) {
                $submission = FormSubmission::query()->find($submissionId);
                if ($submission !== null) {
                    if (blank($submission->outbound_message_id)) {
                        $submission->forceFill(['outbound_message_id' => $message->id])->save();
                    }

                    return $submission;
                }
            }

            $signupFormId = (int) ($meta['signup_form_id'] ?? 0);
            if ($signupFormId > 0) {
                $phone = PhoneNormalizer::normalize((string) ($item['To'] ?? $item['to'] ?? $message->conversation?->contact_phone ?? ''));
                if ($phone !== null) {
                    $byFormPhone = FormSubmission::query()
                        ->where('signup_form_id', $signupFormId)
                        ->whereIn('phone', PhoneNormalizer::lookupVariants($phone))
                        ->whereIn('message_status', ['pending', 'sent', 'delivered'])
                        ->orderByDesc('id')
                        ->first();
                    if ($byFormPhone !== null) {
                        return $byFormPhone;
                    }
                }
            }
        }

        // 2) Provider MessageId stored on submission (legacy msg_id).
        $byExternalId = FormSubmission::query()
            ->where('external_message_id', $messageId)
            ->orderByDesc('id')
            ->first();
        if ($byExternalId !== null) {
            return $byExternalId;
        }

        if ($message !== null) {
            $byLocalId = FormSubmission::query()
                ->where('external_message_id', (string) $message->id)
                ->orderByDesc('id')
                ->first();
            if ($byLocalId !== null) {
                return $byLocalId;
            }
        }

        $to = PhoneNormalizer::normalize((string) ($item['To'] ?? $item['to'] ?? ''));
        if ($to === null) {
            return null;
        }

        $variants = PhoneNormalizer::lookupVariants($to);

        return FormSubmission::query()
            ->whereIn('phone', $variants)
            ->whereIn('message_status', ['pending', 'sent', 'delivered'])
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractFailureReason(array $item): string
    {
        foreach ([
            'ErrorDescription',
            'errorDescription',
            'error_description',
            'ErrorMsg',
            'error_message',
            'failed_reason',
            'FailedReason',
        ] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        $code = trim((string) ($item['ErrorCode'] ?? $item['errorCode'] ?? ''));
        if ($code !== '') {
            return mb_substr('Error code: '.$code, 0, 255);
        }

        return 'Delivery failed';
    }
}
