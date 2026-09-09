<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Services\OptInTemplateService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Models\Contact;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

class OptInMessageService
{
    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_FAILED = 'failed';

    public function __construct(
        private readonly OptInTemplateService $templateService,
        private readonly InboxConversationService $conversationService,
        private readonly InboxOutboundService $outboundService,
    ) {}

    /**
     * Send opt-in template once per contact when send_opt_in_message=yes.
     */
    public function sendOptInToContact(Contact $contact, ?WhatsappLine $line = null): bool
    {
        if (($contact->send_opt_in_message ?? 'no') !== 'yes') {
            return false;
        }

        if ((bool) $contact->opt_in_message_sent) {
            return false;
        }

        if ($contact->opt_in_message_delivery_status === self::DELIVERY_PENDING) {
            return false;
        }

        if ($contact->tags()->where('name', NonWhatsAppNumberService::TAG)->exists()) {
            return false;
        }

        $line ??= WhatsappLine::query()->where('is_default', true)->first()
            ?? WhatsappLine::query()->orderBy('id')->first();

        if ($line === null) {
            Log::warning('Opt-in send skipped: no WhatsApp line', ['contact_id' => $contact->id]);

            return false;
        }

        $template = $this->templateService->ensureTemplate();
        $templateCode = CamsTemplateIdentity::code($template->code, $template->name);
        if ($templateCode === null) {
            Log::warning('Opt-in send skipped: template code missing', ['template_id' => $template->id]);

            return false;
        }

        try {
            $conversation = $this->conversationService->findOrCreateConversation(
                $line,
                (string) $contact->phone,
                $contact->name,
            );

            $message = $this->outboundService->sendTemplate(
                conversation: $conversation,
                templateCode: $templateCode,
                templateParams: [
                    'body' => [
                        'full_name' => $contact->name ?: $contact->phone,
                    ],
                ],
                language: CamsTemplateIdentity::language($template->language),
                sendImmediately: true,
            );

            $contact->forceFill([
                'send_opt_in_message' => 'yes',
                'opt_in_message_sent' => true,
                'opt_in_message_sent_at' => now(),
                'opt_in_message_delivery_status' => self::DELIVERY_PENDING,
                'opt_in_message_delivery_error' => null,
            ])->save();

            $meta = is_array($message->metadata) ? $message->metadata : [];
            $meta['opt_in_contact_id'] = $contact->id;
            $message->forceFill(['metadata' => $meta])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Opt-in send failed', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            $contact->forceFill([
                'send_opt_in_message' => 'yes',
                'opt_in_message_sent' => true,
                'opt_in_message_sent_at' => now(),
                'opt_in_message_delivery_status' => self::DELIVERY_FAILED,
                'opt_in_message_delivery_error' => $e->getMessage(),
            ])->save();

            return false;
        }
    }
}
