<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Billing\Services\WalletService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Enums\ContactOptInStatus;
use App\Enums\MessageDirection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\MailList;
use App\Models\Message;
use App\Models\TriggerVariable;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TriggerTemplateEngine
{
    public function __construct(
        private readonly TriggerVariableQueryService $queryService,
        private readonly TriggerMatcherService $matcherService,
        private readonly InboxOutboundService $outboundService,
        private readonly WalletService $walletService,
    ) {}

    public function process(Conversation $conversation, Message $inboundMessage): TriggerFireResult
    {
        if ($inboundMessage->direction !== MessageDirection::Inbound) {
            return TriggerFireResult::NoMatch;
        }

        $body = trim((string) $inboundMessage->body);

        if ($body === '') {
            return TriggerFireResult::NoMatch;
        }

        if ($this->walletService->balance() <= (float) config('trigger-template.wallet_min_balance', 50)) {
            return TriggerFireResult::WalletBlocked;
        }

        $triggers = $this->queryService->forLine((int) $conversation->whatsapp_line_id);
        $isFirstMessage = $this->isFirstInboundMessage($conversation, $inboundMessage);

        $trigger = $this->matcherService->match($triggers, $body, $isFirstMessage);

        if ($trigger === null) {
            return TriggerFireResult::NoMatch;
        }

        return $this->fire($conversation, $trigger);
    }

    private function fire(Conversation $conversation, TriggerVariable $trigger): TriggerFireResult
    {
        try {
            $this->enrollContactInList($conversation, $trigger);

            $this->outboundService->sendTemplate(
                conversation: $conversation,
                templateCode: $trigger->template_code,
                templateParams: [],
            );

            $this->markConversationRead($conversation);

            return TriggerFireResult::Fired;
        } catch (\Throwable $e) {
            Log::warning('Trigger template send failed', [
                'trigger_id' => $trigger->id,
                'variable_name' => $trigger->variable_name,
                'template_code' => $trigger->template_code,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return TriggerFireResult::SendFailed;
        }
    }

    private function enrollContactInList(Conversation $conversation, TriggerVariable $trigger): void
    {
        if (blank($trigger->list_id)) {
            return;
        }

        $list = MailList::query()->find($trigger->list_id);
        if (! $list instanceof MailList) {
            return;
        }

        $phone = PhoneNormalizer::normalize((string) ($conversation->contact_phone ?? ''))
            ?? trim((string) ($conversation->contact_phone ?? ''));

        if ($phone === '') {
            return;
        }

        try {
            Contact::query()->firstOrCreate(
                [
                    'mail_list_id' => $list->id,
                    'phone' => $phone,
                ],
                [
                    'name' => (string) ($conversation->contact_name ?? $phone),
                    'status' => ContactStatus::Subscribed,
                    'opt_in_status' => ContactOptInStatus::OptedIn,
                    'opted_in_at' => now(),
                    'send_opt_in_message' => 'no',
                    'source' => 'trigger_template',
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Trigger template list enrollment failed', [
                'trigger_id' => $trigger->id,
                'list_id' => $trigger->list_id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isFirstInboundMessage(Conversation $conversation, Message $currentMessage): bool
    {
        return ! Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound)
            ->where('id', '!=', $currentMessage->id)
            ->exists();
    }

    private function markConversationRead(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->forceFill(['unread_count' => 0])->save();
        });
    }
}
