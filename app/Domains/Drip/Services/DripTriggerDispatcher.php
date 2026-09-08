<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Drip\Support\DripTriggerCatalog;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatAction;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Models\DripCampaignState;
use App\Models\WhatsappLine;

class DripTriggerDispatcher
{
    public function __construct(
        private readonly InboxConversationService $conversationService,
        private readonly DripCampaignStatService $statService,
    ) {}

    public function dispatchForContact(string $triggerType, Contact $contact): void
    {
        $normalizedTrigger = DripTriggerCatalog::normalizeType($triggerType);

        $campaign = DripCampaign::query()
            ->active()
            ->where('trigger_type', $normalizedTrigger)
            ->when(
                $contact->mail_list_id,
                fn ($query) => $query->where('audience_id', $contact->mail_list_id),
            )
            ->orderByDesc('id')
            ->first();

        if ($campaign === null || ! $campaign->isWithinDateRange() || ! $campaign->hasFlowData()) {
            return;
        }

        $startNodeId = $this->resolveStartNodeId($campaign);

        if ($startNodeId === null) {
            return;
        }

        $line = WhatsappLine::query()->where('is_default', true)->first();

        if ($line === null) {
            return;
        }

        $conversation = $this->conversationService->findOrCreateConversation(
            line: $line,
            contactPhone: (string) $contact->phone,
            contactName: $contact->name,
        );

        $state = DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => $startNodeId,
            'variables' => [
                'contact_id' => $contact->id,
                'contact_phone' => $contact->phone,
            ],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addDays((int) config('chatbot.drip.state_ttl_days', 7)),
        ]);

        $this->statService->record(
            campaignId: (int) $campaign->id,
            nodeId: $startNodeId,
            nodeType: 'trigger',
            action: ChatbotFlowStatAction::Entered,
            conversation: $conversation,
            contactPhone: (string) $contact->phone,
        );

        ExecuteDripStepJob::dispatch($state->id)
            ->onQueue((string) config('chatbot.drip.queue', 'default'));
    }

    private function resolveStartNodeId(DripCampaign $campaign): ?string
    {
        $nodes = (array) ($campaign->exported_data['nodes'] ?? []);

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = (string) ($node['id'] ?? '');

            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }
}
