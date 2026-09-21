<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Drip\Support\DripTriggerCatalog;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Enums\ChatbotFlowStatAction;
use App\Enums\ChatbotFlowStateStatus;
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

    public function dispatchForContact(
        string $triggerType,
        Contact $contact,
        bool $force = false,
        ?string $enrollmentKey = null,
        ?string $tag = null,
    ): void {
        $normalizedTrigger = DripTriggerCatalog::normalizeType($triggerType);

        $campaigns = DripCampaign::query()
            ->active()
            ->where('trigger_type', $normalizedTrigger)
            ->when(
                $contact->mail_list_id,
                fn ($query) => $query->where(function ($query) use ($contact): void {
                    $query->where('audience_id', $contact->mail_list_id)
                        ->orWhereNull('audience_id');
                }),
            )
            ->orderByDesc('id')
            ->get();

        foreach ($campaigns as $campaign) {
            if ($tag !== null && $normalizedTrigger === 'tag-added') {
                $expected = strtolower(trim((string) data_get($campaign->trigger_options, 'tag_name')));
                if ($expected === '' || $expected !== strtolower(trim($tag))) {
                    continue;
                }
            }

            $this->enroll($campaign, $contact, $force, $enrollmentKey ?? ($tag !== null ? 'tag:'.strtolower(trim($tag)) : null));
        }
    }

    public function enroll(
        DripCampaign $campaign,
        Contact $contact,
        bool $force = false,
        ?string $enrollmentKey = null,
    ): bool {
        if (! $campaign->isActive() || ! $campaign->isWithinDateRange() || ! $campaign->hasFlowData()) {
            return false;
        }

        if ($campaign->audience_id !== null && (int) $campaign->audience_id !== (int) $contact->mail_list_id) {
            return false;
        }

        $startNodeId = $this->resolveStartNodeId($campaign);
        if ($startNodeId === null) {
            return false;
        }

        $line = WhatsappLine::query()->where('is_default', true)->first()
            ?? WhatsappLine::query()->orderBy('id')->first();

        if ($line === null) {
            return false;
        }

        $conversation = $this->conversationService->findOrCreateConversation(
            line: $line,
            contactPhone: (string) $contact->phone,
            contactName: $contact->name,
        );

        if (! $force && $this->alreadyEnrolled((int) $campaign->id, (int) $conversation->id, $enrollmentKey)) {
            return false;
        }

        $variables = [
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ];
        if ($enrollmentKey !== null && $enrollmentKey !== '') {
            $variables['enrollment_key'] = $enrollmentKey;
        }

        $state = DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => $startNodeId,
            'variables' => $variables,
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addDays((int) config('chatbot.drip.state_ttl_days', 30)),
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

        return true;
    }

    private function alreadyEnrolled(int $campaignId, int $conversationId, ?string $enrollmentKey): bool
    {
        $query = DripCampaignState::query()
            ->where('drip_campaign_id', $campaignId)
            ->where('conversation_id', $conversationId);

        if ($enrollmentKey !== null && $enrollmentKey !== '') {
            $query->where('variables->enrollment_key', $enrollmentKey);
        }

        return $query->exists();
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
