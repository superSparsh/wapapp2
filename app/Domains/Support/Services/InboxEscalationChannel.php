<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Inbox\Services\InboxAssignmentService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Inbox\Services\InboxResponseTypeService;
use App\Domains\Support\Contracts\SupportEscalationChannel;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ConversationResponseType;
use App\Models\AiSetting;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

/**
 * Escalate a support chat into the WapApp Inbox human queue.
 */
class InboxEscalationChannel implements SupportEscalationChannel
{
    public function __construct(
        private readonly InboxResponseTypeService $responseTypeService,
        private readonly InboxAssignmentService $assignmentService,
        private readonly InboxOutboundService $outboundService,
    ) {}

    public function escalate(Conversation $conversation, array $context = []): array
    {
        $sendConfirmation = (bool) ($context['send_confirmation'] ?? true);
        $confirmationMessage = trim((string) ($context['confirmation_message'] ?? ''));
        if ($confirmationMessage === '') {
            $confirmationMessage = (string) config(
                'support.handoff.confirmation_message',
                'Humara agent jaldi aapse connect karega. Aap Inbox queue mein ho.',
            );
        }

        $hadActiveChatbot = $this->hasActiveChatbotOwnership($conversation);
        $alreadyEscalated = $conversation->response_type === ConversationResponseType::Human
            && ! $hadActiveChatbot;

        if (AiSetting::getBool('ai_auto_response_enabled', false)) {
            Log::warning('Support handoff while ai_auto_response_enabled is on — human takeover may be overridden by global AI. Turn it off for the support tenant.', [
                'conversation_id' => $conversation->id,
            ]);
        }

        $this->responseTypeService->setForConversation(
            $conversation,
            ConversationResponseType::Human,
        );

        // Clear sticky bot so inbound AI routing does not keep treating this as an AI chat
        // when global auto-reply is off (and reduce surprise if it is on).
        $conversation = $conversation->refresh();
        if ($conversation->active_ai_bot_id !== null) {
            $conversation->forceFill(['active_ai_bot_id' => null])->save();
        }

        // Leave unassigned so agents pick from the Inbox Unassigned filter.
        $this->assignmentService->assign($conversation->refresh(), 'unassigned');

        $this->completeActiveChatbotStates($conversation);

        $messageSent = false;
        if ($sendConfirmation) {
            $body = $alreadyEscalated
                ? (string) config(
                    'support.handoff.already_queued_message',
                    'Aap pehle se agent queue mein ho. Humara team jaldi reply karega.',
                )
                : $confirmationMessage;

            $this->outboundService->sendText(
                $conversation->refresh(),
                $body,
                enforceWindow: false,
            );
            $messageSent = true;
        }

        return [
            'channel' => 'inbox',
            'conversation_id' => (int) $conversation->id,
            'already_escalated' => $alreadyEscalated,
            'message_sent' => $messageSent,
            'end_flow' => true,
        ];
    }

    private function hasActiveChatbotOwnership(Conversation $conversation): bool
    {
        return ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
                ChatbotFlowStateStatus::Delayed->value,
            ])
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function completeActiveChatbotStates(Conversation $conversation): void
    {
        ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
                ChatbotFlowStateStatus::Delayed->value,
            ])
            ->each(function (ChatbotFlowState $state): void {
                $state->markCompleted();
            });
    }
}
