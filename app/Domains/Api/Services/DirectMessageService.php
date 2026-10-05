<?php

declare(strict_types=1);

namespace App\Domains\Api\Services;

use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Template;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

/**
 * Partner API: send a single approved template and poll delivery status.
 * Reuses the inbox outbound pipeline so wallet charges follow the same Delivered/Read path.
 */
class DirectMessageService
{
    private const RESERVED_INPUT_KEYS = [
        'api_token',
        'template_uid',
        'to',
        'from',
        'first_name',
        'last_name',
        'variable_name',
        'variables',
        'template_variables',
        'message_id',
    ];

    public function __construct(
        private readonly InboxConversationService $conversationService,
        private readonly InboxOutboundService $outboundService,
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     success: true,
     *     message: string,
     *     message_id: string,
     *     status: string,
     *     external_message_id: string|null,
     *     to: string,
     *     from: string
     * }
     */
    public function send(array $input): array
    {
        $to = $this->normalizeRequiredPhone((string) ($input['to'] ?? ''), 'to');
        $fromRaw = trim((string) ($input['from'] ?? ''));
        $line = $this->resolveLine($fromRaw !== '' ? $fromRaw : null);
        $template = $this->resolveApprovedTemplate((string) ($input['template_uid'] ?? ''));
        $templateCode = $this->providerCode($template);

        if ($this->camsClient->isConfigured()) {
            if (blank($line->alibaba_cust_space_id)) {
                throw ValidationException::withMessages([
                    'from' => 'Selected sender number is missing Alibaba Cust Space ID. Configure the WhatsApp line first.',
                ]);
            }
        }

        $contactName = $this->contactName($input);
        $conversation = $this->conversationService->findOrCreateConversation(
            line: $line,
            contactPhone: $to,
            contactName: $contactName,
        );

        $params = $this->templateParams($input, $to, $contactName);

        $message = $this->outboundService->sendTemplate(
            conversation: $conversation,
            templateCode: $templateCode,
            templateParams: $params,
            language: CamsTemplateIdentity::language($template->language),
            sendImmediately: true,
            extraMetadata: [
                'wallet_source' => 'api',
                'wallet_source_label' => 'API Direct Message',
                'billable' => true,
                'template_category' => strtoupper((string) ($template->category ?? 'MARKETING')),
                'template_id' => $template->id,
                'contact_phone' => $to,
                'api_send' => true,
            ],
        );

        $message->refresh();

        if ($message->status === MessageStatus::Failed) {
            throw ValidationException::withMessages([
                'to' => $this->friendlyFailure((string) ($message->failed_reason ?? '')),
            ]);
        }

        if ($message->status !== MessageStatus::Sent) {
            throw ValidationException::withMessages([
                'to' => 'Message could not be confirmed as sent. Please try again.',
            ]);
        }

        return [
            'success' => true,
            'message' => 'Message sent successfully.',
            'message_id' => (string) $message->uuid,
            'status' => $message->status->value,
            'external_message_id' => $message->external_message_id
                ? (string) $message->external_message_id
                : null,
            'to' => $to,
            'from' => PhoneNormalizer::normalize((string) $line->phone) ?? (string) $line->phone,
        ];
    }

    /**
     * @return array{
     *     success: true,
     *     message_id: string,
     *     status: string,
     *     external_message_id: string|null,
     *     failed_reason: string|null,
     *     sent_at: string|null,
     *     delivered_at: string|null,
     *     read_at: string|null,
     *     to: string|null,
     *     from: string|null
     * }
     */
    public function status(string $messageId): array
    {
        $messageId = trim($messageId);
        if ($messageId === '') {
            throw ValidationException::withMessages([
                'message_id' => 'message_id is required.',
            ]);
        }

        $message = $this->findMessage($messageId);
        if ($message === null) {
            abort(404, 'Message not found.');
        }

        $message->loadMissing('conversation.whatsappLine');

        $to = $message->conversation?->contact_phone;
        $from = $message->conversation?->whatsappLine?->phone
            ?? $message->conversation?->line_phone;

        return [
            'success' => true,
            'message_id' => (string) ($message->uuid ?? $message->id),
            'status' => $message->status instanceof MessageStatus
                ? $message->status->value
                : (string) $message->status,
            'external_message_id' => $message->external_message_id
                ? (string) $message->external_message_id
                : null,
            'failed_reason' => $message->failed_reason
                ? (string) $message->failed_reason
                : null,
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
            'delivered_at' => optional($message->delivered_at)?->toIso8601String(),
            'read_at' => optional($message->read_at)?->toIso8601String(),
            'to' => $to ? (string) $to : null,
            'from' => $from
                ? (PhoneNormalizer::normalize((string) $from) ?? (string) $from)
                : null,
        ];
    }

    private function resolveLine(?string $from): WhatsappLine
    {
        $query = WhatsappLine::query()->orderByDesc('is_default')->orderBy('id');

        if ($from !== null && $from !== '') {
            $variants = PhoneNormalizer::lookupVariants($from);
            if ($variants === []) {
                throw ValidationException::withMessages([
                    'from' => 'Enter a valid sender WhatsApp number.',
                ]);
            }

            $line = (clone $query)->whereIn('phone', $variants)->first();
            if (! $line instanceof WhatsappLine) {
                throw ValidationException::withMessages([
                    'from' => 'Sender WhatsApp number is not registered on this account.',
                ]);
            }

            return $line;
        }

        $line = $query->first();
        if (! $line instanceof WhatsappLine) {
            throw ValidationException::withMessages([
                'from' => 'No WhatsApp number is configured for this account.',
            ]);
        }

        return $line;
    }

    private function resolveApprovedTemplate(string $templateUid): Template
    {
        $templateUid = trim($templateUid);
        if ($templateUid === '') {
            throw ValidationException::withMessages([
                'template_uid' => 'template_uid is required.',
            ]);
        }

        $template = Template::query()->where('uuid', $templateUid)->first();
        if (! $template instanceof Template && ctype_digit($templateUid)) {
            $template = Template::query()->find((int) $templateUid);
        }

        if (! $template instanceof Template) {
            throw ValidationException::withMessages([
                'template_uid' => 'Template not found.',
            ]);
        }

        if ($template->status !== TemplateStatus::Approved) {
            throw ValidationException::withMessages([
                'template_uid' => 'Template must be approved before it can be sent via API.',
            ]);
        }

        return $template;
    }

    private function providerCode(Template $template): string
    {
        $payload = is_array($template->payload) ? $template->payload : [];
        $code = $template->whatsappCode()
            ?? CamsTemplateIdentity::code(
                is_string($payload['legacy_template_code'] ?? null) ? $payload['legacy_template_code'] : null,
                is_string(data_get($payload, 'meta.archived_code')) ? data_get($payload, 'meta.archived_code') : null,
            );

        if ($code === null || ! CamsTemplateIdentity::isProviderCode($code)) {
            throw ValidationException::withMessages([
                'template_uid' => 'Selected template is missing a valid WhatsApp template code.',
            ]);
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function contactName(array $input): ?string
    {
        $name = trim(((string) ($input['first_name'] ?? '')).' '.((string) ($input['last_name'] ?? '')));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function templateParams(array $input, string $to, ?string $contactName): array
    {
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $full = $contactName ?: trim($first.' '.$last);

        $params = array_filter([
            'phone' => $to,
            'full_name' => $full !== '' ? $full : null,
            'name' => $full !== '' ? $full : null,
            'first_name' => $first !== '' ? $first : null,
            'last_name' => $last !== '' ? $last : null,
        ], static fn ($value) => $value !== null && $value !== '');

        foreach (['variable_name', 'variables', 'template_variables'] as $bagKey) {
            $bag = $input[$bagKey] ?? null;
            if (! is_array($bag)) {
                continue;
            }
            foreach ($bag as $key => $value) {
                if (! is_string($key) && ! is_int($key)) {
                    continue;
                }
                if (is_scalar($value) || $value === null) {
                    $params[(string) $key] = $value;
                }
            }
        }

        foreach ($input as $key => $value) {
            if (! is_string($key) || in_array($key, self::RESERVED_INPUT_KEYS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function normalizeRequiredPhone(string $phone, string $field): string
    {
        $normalized = PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone) ?? '';
        if ($normalized === '') {
            throw ValidationException::withMessages([
                $field => 'Enter a valid phone number with country code (without +).',
            ]);
        }

        return $normalized;
    }

    private function findMessage(string $messageId): ?Message
    {
        $byUuid = Message::query()->where('uuid', $messageId)->first();
        if ($byUuid instanceof Message) {
            return $byUuid;
        }

        $byExternal = Message::query()->where('external_message_id', $messageId)->first();
        if ($byExternal instanceof Message) {
            return $byExternal;
        }

        if (ctype_digit($messageId)) {
            $byId = Message::query()->find((int) $messageId);
            if ($byId instanceof Message) {
                return $byId;
            }
        }

        return null;
    }

    private function friendlyFailure(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return 'WhatsApp provider rejected the message.';
        }

        if (str_starts_with($reason, 'CAMS request failed:')) {
            return 'WhatsApp provider rejected the message. '.$reason;
        }

        return $reason;
    }
}
