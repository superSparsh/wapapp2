<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\Template;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

class CampaignTestMessageService
{
    public function __construct(
        private readonly InboxConversationService $conversationService,
        private readonly InboxOutboundService $outboundService,
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * @param  array<string, mixed>  $templateVariables
     */
    public function send(Campaign $campaign, string $phone, array $templateVariables = []): Message
    {
        $line = $campaign->whatsappLine ?? WhatsappLine::query()->where('is_default', true)->first();
        $template = $campaign->template;

        abort_if($line === null, 422, 'WhatsApp line is required.');
        abort_if($template === null, 422, 'Template is required.');

        return $this->sendDirect(
            line: $line,
            template: $template,
            phone: $phone,
            templateVariables: $templateVariables ?: (array) ($campaign->template_variables ?? []),
        );
    }

    /**
     * Live CAMS template send using an explicit line + template (wizard test message).
     * Sends immediately (sync) so the UI can show a real success or failure reason.
     *
     * @param  array<string, mixed>  $templateVariables
     */
    public function sendDirect(
        WhatsappLine $line,
        Template $template,
        string $phone,
        array $templateVariables = [],
    ): Message {
        $normalized = PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone) ?? $phone;
        abort_if($normalized === null || $normalized === '', 422, 'Enter a valid phone number with country code.');

        $templateCode = CamsTemplateIdentity::code(
            $template->code,
            is_array($template->payload) ? ($template->payload['legacy_template_code'] ?? null) : null,
        );
        abort_if(
            $templateCode === null,
            422,
            'Selected template is missing a valid WhatsApp template_code. Re-import from legacy (must have template_code) or update the template code.',
        );

        if ($this->camsClient->isConfigured()) {
            abort_if(
                blank($line->alibaba_cust_space_id),
                422,
                'Selected From Number is missing Alibaba Cust Space ID. Configure the WhatsApp line first.',
            );
        }

        $conversation = $this->conversationService->findOrCreateConversation(
            line: $line,
            contactPhone: $normalized,
        );

        $params = array_merge(
            [
                'full_name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
                'name' => 'Test User',
                'phone' => $normalized,
                'unsub' => 'test',
            ],
            array_filter(
                $templateVariables,
                static fn ($value) => is_scalar($value) || $value === null,
            ),
        );

        $message = $this->outboundService->sendTemplate(
            conversation: $conversation,
            templateCode: $templateCode,
            templateParams: $params,
            language: CamsTemplateIdentity::language($template->language),
            sendImmediately: true,
        );

        $message->refresh();

        if ($message->status === MessageStatus::Failed) {
            abort(422, $this->friendlyFailure((string) ($message->failed_reason ?? '')));
        }

        if ($message->status !== MessageStatus::Sent) {
            abort(422, 'Test message could not be confirmed as sent. Please try again.');
        }

        return $message;
    }

    private function friendlyFailure(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            return 'WhatsApp provider rejected the test message.';
        }

        if (str_starts_with($reason, 'CAMS request failed:')) {
            return 'WhatsApp provider rejected the test message. '.$reason;
        }

        return $reason;
    }
}
