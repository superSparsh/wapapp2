<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Enums\MessageDirection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TriggerVariable;
use Illuminate\Support\Facades\DB;

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
            $this->outboundService->sendTemplate(
                conversation: $conversation,
                templateCode: $trigger->template_code,
                templateParams: [],
            );

            $this->markConversationRead($conversation);

            return TriggerFireResult::Fired;
        } catch (\Throwable) {
            return TriggerFireResult::SendFailed;
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
