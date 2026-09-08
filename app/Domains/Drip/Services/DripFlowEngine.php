<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatAction;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DripCampaignState;
use App\Models\Message;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DripFlowEngine
{
    private const MAX_ITERATIONS = 50;

    public function __construct(
        private readonly InboxOutboundService $outboundService,
        private readonly DripCampaignStatService $statService,
        private readonly DripContactOperationService $contactOperationService,
        private readonly FlowVariableResolver $variableResolver,
        private readonly WhatsappFlowInteractiveService $flowInteractiveService,
    ) {}

    public function executeFromState(DripCampaignState $state): void
    {
        $state->loadMissing('dripCampaign', 'conversation.contact');
        $campaign = $state->dripCampaign;
        $conversation = $state->conversation;

        if ($campaign === null || $conversation === null || ! $campaign->isActive() || ! $campaign->hasFlowData()) {
            $this->completeState($state);

            return;
        }

        $exported = (array) $campaign->exported_data;
        $nodesList = (array) ($exported['nodes'] ?? []);
        $nodeMap = $this->buildNodeMap($nodesList);
        $edges = (array) ($exported['edges'] ?? []);
        $currentNodeId = $state->current_node_id;

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            if ($currentNodeId === null || $currentNodeId === '') {
                $this->completeState($state);

                return;
            }

            $node = $nodeMap[$currentNodeId] ?? null;

            if ($node === null) {
                $this->completeState($state);

                return;
            }

            $type = $this->resolveNodeType($node);
            $nodeData = (array) ($node['data'] ?? $node);

            // 1. Template Message
            if ($type === 'templateMessage') {
                $this->processTemplateMessage($state, $conversation, $node);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 2. Delay / Wait
            if ($type === 'delay') {
                $delaySeconds = $this->resolveDelaySeconds($node);
                $nextNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);

                $state->forceFill([
                    'current_node_id' => $nextNodeId,
                    'status' => ChatbotFlowStateStatus::Waiting,
                    'expires_at' => now()->addSeconds($delaySeconds),
                ])->save();

                $this->statService->record(
                    campaignId: (int) $state->drip_campaign_id,
                    nodeId: (string) ($node['id'] ?? 'delay'),
                    nodeType: 'delay',
                    action: ChatbotFlowStatAction::Completed,
                    conversation: $conversation,
                    metadata: ['delay_seconds' => $delaySeconds],
                );

                ExecuteDripStepJob::dispatch($state->id)
                    ->delay(now()->addSeconds($delaySeconds))
                    ->onQueue((string) config('chatbot.drip.queue', 'default'));

                return;
            }

            // 3. Condition / Enhanced Condition
            if ($type === 'condition' || $type === 'enhancedCondition') {
                $evalResult = $type === 'enhancedCondition'
                    ? $this->evaluateEnhancedCondition($state, $conversation, $nodeData)
                    : $this->evaluateCondition($state, $conversation, $nodeData);
                $waitSeconds = $this->resolveConditionWaitSeconds($nodeData);
                $waitFlagKey = 'cond_waited_' . $currentNodeId;
                $variables = (array) ($state->variables ?? []);

                if ($evalResult) {
                    // Condition is TRUE -> take YES branch
                    $this->statService->record(
                        campaignId: (int) $state->drip_campaign_id,
                        nodeId: (string) ($node['id'] ?? 'condition'),
                        nodeType: $type,
                        action: ChatbotFlowStatAction::Completed,
                        conversation: $conversation,
                        metadata: ['result' => 'yes', 'condition_type' => $nodeData['condition_type'] ?? 'custom'],
                    );

                    unset($variables[$waitFlagKey]);
                    $state->forceFill(['variables' => $variables]);

                    $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId, 'yes');
                    $state->forceFill(['current_node_id' => $currentNodeId])->save();

                    continue;
                }

                // Condition is FALSE: check if we should wait
                $alreadyWaited = ! empty($variables[$waitFlagKey]) || ($state->expires_at !== null && now()->gte($state->expires_at));

                if (! $alreadyWaited && $waitSeconds > 0 && $type === 'condition') {
                    $variables[$waitFlagKey] = true;
                    $state->forceFill([
                        'variables' => $variables,
                        'status' => ChatbotFlowStateStatus::Waiting,
                        'expires_at' => now()->addSeconds($waitSeconds),
                    ])->save();

                    ExecuteDripStepJob::dispatch($state->id)
                        ->delay(now()->addSeconds($waitSeconds))
                        ->onQueue((string) config('chatbot.drip.queue', 'default'));

                    return;
                }

                // Timeout expired & still FALSE -> take NO branch
                $this->statService->record(
                    campaignId: (int) $state->drip_campaign_id,
                    nodeId: (string) ($node['id'] ?? 'condition'),
                    nodeType: $type,
                    action: ChatbotFlowStatAction::Completed,
                    conversation: $conversation,
                    metadata: ['result' => 'no', 'condition_type' => $nodeData['condition_type'] ?? 'custom'],
                );

                unset($variables[$waitFlagKey]);
                $state->forceFill(['variables' => $variables]);

                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId, 'no');
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 4. Contact Operation (Tag, Copy, Move, Update)
            if ($type === 'contactOperation') {
                $this->processContactOperation($state, $conversation, $nodeData);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 5. Welcome Message / Text Message
            if ($type === 'welcomeMessage') {
                $messageText = (string) ($nodeData['message'] ?? $nodeData['text'] ?? '');
                if ($messageText !== '') {
                    $this->outboundService->sendText($conversation, $messageText, enforceWindow: false);
                    $this->statService->record(
                        campaignId: (int) $state->drip_campaign_id,
                        nodeId: (string) ($node['id'] ?? 'welcomeMessage'),
                        nodeType: 'welcomeMessage',
                        action: ChatbotFlowStatAction::Completed,
                        conversation: $conversation,
                    );
                }
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 6. Interactive Message (Buttons / Lists)
            if ($type === 'interactiveMessage') {
                $options = (array) ($nodeData['options'] ?? []);
                $interactiveType = (string) ($nodeData['interactive_type'] ?? 'button');
                $messageText = (string) ($nodeData['message'] ?? '');

                if ($messageText !== '' && ! empty($options)) {
                    $interactivePayload = [
                        'type' => $interactiveType === 'list' ? 'list' : 'button',
                        'body' => ['text' => $messageText],
                        'action' => [
                            'buttons' => array_map(fn ($opt, $idx) => [
                                'type' => 'reply',
                                'reply' => ['id' => 'btn_'.$idx, 'title' => (string) $opt],
                            ], $options, array_keys($options)),
                        ],
                    ];
                    $this->outboundService->sendInteractive($conversation, $interactivePayload, previewBody: $messageText, enforceWindow: false);
                    $this->statService->record(
                        campaignId: (int) $state->drip_campaign_id,
                        nodeId: (string) ($node['id'] ?? 'interactiveMessage'),
                        nodeType: 'interactiveMessage',
                        action: ChatbotFlowStatAction::Completed,
                        conversation: $conversation,
                    );
                }
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 7. Jump To Step
            if ($type === 'jumpToStep') {
                $targetId = (string) ($nodeData['target_node'] ?? '');
                $currentNodeId = ($targetId !== '' && isset($nodeMap[$targetId]))
                    ? $targetId
                    : $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 8. Wait for response
            if ($type === 'waitForResponse') {
                $timeout = (int) ($nodeData['timeout'] ?? 120);
                $nextNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);

                $state->forceFill([
                    'current_node_id' => $nextNodeId,
                    'status' => ChatbotFlowStateStatus::Waiting,
                    'expires_at' => now()->addSeconds($timeout),
                ])->save();

                return;
            }

            // 9. Media Message
            if ($type === 'mediaMessage') {
                $this->processMediaMessage($state, $conversation, $node, $nodeData);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 10. HTTP Request
            if ($type === 'httpRequest') {
                $this->processHttpRequest($state, $conversation, $node, $nodeData);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 11. Typing Indicator
            if ($type === 'typingIndicator') {
                $durationSeconds = (int) ($nodeData['delay_seconds'] ?? $nodeData['duration'] ?? $nodeData['durationSeconds'] ?? 3);
                $durationSeconds = max(1, min(25, $durationSeconds));
                $nextNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);

                $this->outboundService->sendTypingIndicator($conversation);
                $this->statService->record(
                    campaignId: (int) $state->drip_campaign_id,
                    nodeId: (string) ($node['id'] ?? 'typingIndicator'),
                    nodeType: 'typingIndicator',
                    action: ChatbotFlowStatAction::Completed,
                    conversation: $conversation,
                    metadata: ['duration_seconds' => $durationSeconds],
                );

                $state->forceFill([
                    'current_node_id' => $nextNodeId,
                    'status' => ChatbotFlowStateStatus::Waiting,
                    'expires_at' => now()->addSeconds($durationSeconds),
                ])->save();

                if ($nextNodeId !== null) {
                    ExecuteDripStepJob::dispatch($state->id)
                        ->delay(now()->addSeconds($durationSeconds))
                        ->onQueue((string) config('chatbot.drip.queue', 'default'));
                }

                return;
            }

            // 12. Function Call
            if ($type === 'functionCall') {
                $this->processFunctionCall($state, $conversation, $node, $nodeData);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 13. Carousel Template
            if ($type === 'carouselTemplate') {
                $this->processCarouselTemplate($state, $conversation, $node, $nodeData);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // 14. WhatsApp Flow Template
            if ($type === 'whatsappFlowTemplate') {
                $this->processWhatsappFlowTemplate($state, $conversation, $node, $nodeData);
                $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
                $state->forceFill(['current_node_id' => $currentNodeId])->save();

                continue;
            }

            // Other / fallback — log so unimplemented types are visible
            Log::warning('DripFlowEngine: skipping unsupported node type', [
                'type' => $type,
                'node_id' => $node['id'] ?? null,
                'state_id' => $state->id,
            ]);
            $currentNodeId = $this->nextNodeId($edges, $nodesList, $currentNodeId);
            $state->forceFill(['current_node_id' => $currentNodeId])->save();
        }

        $this->completeState($state);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function buildNodeMap(array $nodes): array
    {
        $map = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = (string) ($node['id'] ?? '');

            if ($id !== '') {
                $map[$id] = $node;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function resolveNodeType(array $node): string
    {
        return (string) ($node['type'] ?? data_get($node, 'data.type', ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<int, array<string, mixed>>  $nodesList
     */
    private function nextNodeId(array $edges, array $nodesList, string $currentNodeId, ?string $branch = null): ?string
    {
        // 1. If branch specified ('yes' or 'no'), check edges with matching sourceHandle
        if ($branch !== null) {
            foreach ($edges as $edge) {
                if (! is_array($edge)) {
                    continue;
                }

                $source = (string) ($edge['source'] ?? '');
                $handle = strtolower((string) ($edge['sourceHandle'] ?? $edge['source_handle'] ?? $edge['handle'] ?? ''));

                if ($source === $currentNodeId && ($handle === strtolower($branch) || $handle === ($branch === 'yes' ? 'true' : 'false'))) {
                    $target = (string) ($edge['target'] ?? '');
                    if ($target !== '') {
                        return $target;
                    }
                }
            }
        }

        // 2. Generic edge from this source
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }

            if ((string) ($edge['source'] ?? '') === $currentNodeId) {
                $target = (string) ($edge['target'] ?? '');

                return $target !== '' ? $target : null;
            }
        }

        // 3. Sequential fallback if no explicit edges exist at all in the flow:
        if (empty($edges)) {
            $currentIndex = null;
            foreach ($nodesList as $idx => $n) {
                if (is_array($n) && (string) ($n['id'] ?? '') === $currentNodeId) {
                    $currentIndex = $idx;
                    break;
                }
            }

            if ($currentIndex !== null && isset($nodesList[$currentIndex + 1])) {
                $next = $nodesList[$currentIndex + 1];
                if (is_array($next) && ! empty($next['id'])) {
                    return (string) $next['id'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function resolveDelaySeconds(array $node): int
    {
        $data = (array) ($node['data'] ?? $node);

        if (! empty($data['delay_seconds'])) {
            return max(1, (int) $data['delay_seconds']);
        }

        $value = (int) ($data['delay_value'] ?? $data['value'] ?? 1);
        $unit = (string) ($data['delay_unit'] ?? $data['unit'] ?? 'minutes');

        return match ($unit) {
            'seconds' => max(1, $value),
            'hours' => max(1, $value) * 3600,
            'days' => max(1, $value) * 86400,
            'weeks' => max(1, $value) * 604800,
            'months' => max(1, $value) * 2592000,
            default => max(1, $value) * 60,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveConditionWaitSeconds(array $data): int
    {
        if (! empty($data['wait_seconds'])) {
            return (int) $data['wait_seconds'];
        }

        $condWait = (string) ($data['condition_wait'] ?? '1 day');

        return match ($condWait) {
            '15 minutes' => 900,
            '1 hour' => 3600,
            '2 hours' => 7200,
            '4 hours' => 14400,
            '8 hours' => 28800,
            '12 hours' => 43200,
            '1 day' => 86400,
            '2 days' => 172800,
            '3 days' => 259200,
            '5 days' => 432000,
            '1 week' => 604800,
            '2 weeks' => 1209600,
            default => (int) ($data['wait_value'] ?? 1) * match ($data['wait_unit'] ?? 'days') {
                'minutes' => 60,
                'hours' => 3600,
                'weeks' => 604800,
                default => 86400,
            },
        };
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    private function evaluateCondition(DripCampaignState $state, Conversation $conversation, array $nodeData): bool
    {
        $conditionType = (string) ($nodeData['condition_type'] ?? '');
        if ($conditionType === '' && ! empty($nodeData['condition_variable'])) {
            $conditionType = 'custom_variable';
        }
        if ($conditionType === '') {
            $conditionType = 'whatsapp_read';
        }

        // 1. Custom variable / contact field evaluation
        if ($conditionType === 'custom_variable') {
            $variable = (string) ($nodeData['condition_variable'] ?? '');
            $operator = (string) ($nodeData['condition_operator'] ?? 'equals');
            $expectedValue = (string) ($nodeData['condition_value'] ?? '');

            $contact = $conversation->contact;
            $customFields = (array) ($contact?->custom_fields ?? []);
            $stateVars = (array) ($state->variables ?? []);

            $actualValue = $contact?->{$variable} ?? $customFields[$variable] ?? $stateVars[$variable] ?? null;

            return $this->compareValues($actualValue, $operator, $expectedValue);
        }

        // 2. WhatsApp message condition evaluation
        $targetTemplate = (string) ($nodeData['target_template'] ?? '');
        $messageQuery = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Outbound)
            ->latest('id');

        if ($targetTemplate !== '') {
            $messageQuery->where(function ($q) use ($targetTemplate) {
                $q->where('body', $targetTemplate)
                    ->orWhere('metadata->template_code', $targetTemplate);
            });
        }

        $message = $messageQuery->first();

        return match ($conditionType) {
            'whatsapp_read' => $message !== null && ($message->read_at !== null || $message->status === MessageStatus::Read),
            'whatsapp_delivered' => $message !== null && ($message->delivered_at !== null || $message->status === MessageStatus::Delivered || $message->status === MessageStatus::Read),
            'whatsapp_unread' => $message !== null && $message->read_at === null && $message->status !== MessageStatus::Read,
            'whatsapp_failed' => $message !== null && ($message->failed_at !== null || $message->status === MessageStatus::Failed),
            'whatsapp_reply' => $this->evaluateReplyCondition($conversation, $message),
            default => true,
        };
    }

    private function evaluateReplyCondition(Conversation $conversation, ?Message $outboundMessage): bool
    {
        if ($conversation->replied_at !== null) {
            if ($outboundMessage?->created_at !== null) {
                return $conversation->replied_at->gte($outboundMessage->created_at);
            }

            return true;
        }

        $inboundQuery = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound);

        if ($outboundMessage?->created_at !== null) {
            $inboundQuery->where('created_at', '>=', $outboundMessage->created_at);
        }

        return $inboundQuery->exists();
    }

    private function compareValues(mixed $actual, string $operator, string $expected): bool
    {
        $actualStr = (string) ($actual ?? '');

        return match ($operator) {
            'equals', '==' => $actualStr === $expected,
            'not_equals', '!=' => $actualStr !== $expected,
            'contains' => str_contains(strtolower($actualStr), strtolower($expected)),
            'greater_than', '>' => (float) $actualStr > (float) $expected,
            'less_than', '<' => (float) $actualStr < (float) $expected,
            'is_empty' => trim($actualStr) === '',
            'not_empty' => trim($actualStr) !== '',
            default => $actualStr === $expected,
        };
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    private function processContactOperation(DripCampaignState $state, Conversation $conversation, array $nodeData): void
    {
        $contact = $conversation->contact;

        if ($contact === null && ! empty($state->variables['contact_id'])) {
            $contact = Contact::query()->find((int) $state->variables['contact_id']);
        }

        if ($contact === null) {
            Log::warning('DripFlowEngine: contact not found for contactOperation', [
                'state_id' => $state->id,
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        $operation = (string) ($nodeData['operation_type'] ?? 'tag');
        $this->contactOperationService->apply($operation, $contact, $nodeData);

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($nodeData['id'] ?? 'contactOperation'),
            nodeType: 'contactOperation',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
            metadata: ['operation' => $operation],
        );
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function processTemplateMessage(
        DripCampaignState $state,
        Conversation $conversation,
        array $node,
    ): void {
        $data = (array) ($node['data'] ?? $node);
        $templateCode = (string) ($data['template_code'] ?? $data['templateCode'] ?? $data['template_name'] ?? $data['template_id'] ?? '');
        $templateParams = (array) ($data['template_params'] ?? $data['templateParams'] ?? $data['variables'] ?? []);
        $language = isset($data['language']) ? (string) $data['language'] : null;

        if ($templateCode === '' && isset($data['template_uid'])) {
            $template = Template::query()->where('uuid', (string) $data['template_uid'])->first();
            $templateCode = (string) ($template?->code ?? $template?->name ?? '');
            $language ??= $template?->language;
        }

        if ($templateCode === '') {
            $this->statService->record(
                campaignId: (int) $state->drip_campaign_id,
                nodeId: (string) ($node['id'] ?? 'unknown'),
                nodeType: 'templateMessage',
                action: ChatbotFlowStatAction::Error,
                conversation: $conversation,
                metadata: ['reason' => 'missing_template'],
            );

            return;
        }

        $message = $this->outboundService->sendTemplate(
            conversation: $conversation,
            templateCode: $templateCode,
            templateParams: $templateParams,
            language: $language,
        );

        $vars = (array) ($state->variables ?? []);
        $vars['last_message_id'] = $message->id;
        $vars['last_template_code'] = $templateCode;
        $vars['last_sent_at'] = now()->toIso8601String();
        $state->forceFill(['variables' => $vars])->save();

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($node['id'] ?? 'unknown'),
            nodeType: 'templateMessage',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $nodeData
     */
    private function processMediaMessage(
        DripCampaignState $state,
        Conversation $conversation,
        array $node,
        array $nodeData,
    ): void {
        $variables = (array) ($state->variables ?? []);
        $mediaUrl = (string) ($nodeData['media_url'] ?? $nodeData['mediaUrl'] ?? $nodeData['fileUrl'] ?? $nodeData['url'] ?? '');
        $mediaType = (string) ($nodeData['media_type'] ?? $nodeData['mediaType'] ?? 'image');
        $caption = (string) ($nodeData['caption'] ?? '');
        $fileName = isset($nodeData['fileName']) ? (string) $nodeData['fileName'] : null;

        if ($mediaUrl === '') {
            $this->statService->record(
                campaignId: (int) $state->drip_campaign_id,
                nodeId: (string) ($node['id'] ?? 'mediaMessage'),
                nodeType: 'mediaMessage',
                action: ChatbotFlowStatAction::Error,
                conversation: $conversation,
                metadata: ['reason' => 'missing_media_url'],
            );

            return;
        }

        $mediaUrl = $this->variableResolver->resolve($mediaUrl, $variables);
        $caption = $caption !== '' ? $this->variableResolver->resolve($caption, $variables) : null;

        $message = $this->outboundService->sendMediaFromUrl(
            conversation: $conversation,
            mediaUrl: $mediaUrl,
            mediaType: $mediaType,
            caption: $caption,
            fileName: $fileName,
            enforceWindow: false,
        );

        $vars = $variables;
        $vars['last_message_id'] = $message->id;
        $vars['last_media_url'] = $mediaUrl;
        $state->forceFill(['variables' => $vars])->save();

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($node['id'] ?? 'mediaMessage'),
            nodeType: 'mediaMessage',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
            metadata: ['media_type' => $mediaType],
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $nodeData
     */
    private function processHttpRequest(
        DripCampaignState $state,
        Conversation $conversation,
        array $node,
        array $nodeData,
    ): void {
        $variables = (array) ($state->variables ?? []);
        $url = $this->variableResolver->resolve((string) ($nodeData['url'] ?? ''), $variables);
        $method = strtoupper((string) ($nodeData['method'] ?? 'GET'));
        $resultVariable = (string) ($nodeData['resultVariable'] ?? $nodeData['result_variable'] ?? 'http_response');

        if ($url === '') {
            $this->statService->record(
                campaignId: (int) $state->drip_campaign_id,
                nodeId: (string) ($node['id'] ?? 'httpRequest'),
                nodeType: 'httpRequest',
                action: ChatbotFlowStatAction::Error,
                conversation: $conversation,
                metadata: ['reason' => 'missing_url'],
            );

            return;
        }

        $headers = [];
        foreach ((array) ($nodeData['headers'] ?? []) as $key => $value) {
            $headers[$this->variableResolver->resolve((string) $key, $variables)] =
                $this->variableResolver->resolve((string) $value, $variables);
        }

        $body = $nodeData['body'] ?? null;
        $bodyString = null;
        if (is_array($body)) {
            $encoded = json_encode($body);
            $bodyString = $encoded !== false ? $this->variableResolver->resolve($encoded, $variables) : null;
        } elseif ($body !== null) {
            $bodyString = $this->variableResolver->resolve((string) $body, $variables);
        }

        try {
            $pending = Http::timeout(15)->withHeaders($headers);
            $response = match ($method) {
                'POST' => $pending->withBody($bodyString ?? '', 'application/json')->post($url),
                'PUT' => $pending->withBody($bodyString ?? '', 'application/json')->put($url),
                'PATCH' => $pending->withBody($bodyString ?? '', 'application/json')->patch($url),
                'DELETE' => $pending->delete($url),
                default => $pending->get($url),
            };

            $variables[$resultVariable] = $response->body();
            $variables["{$resultVariable}_status"] = $response->status();
            $variables["{$resultVariable}_success"] = $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Drip HTTP request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'state_id' => $state->id,
            ]);
            $variables[$resultVariable] = null;
            $variables["{$resultVariable}_status"] = 0;
            $variables["{$resultVariable}_success"] = false;
            $variables["{$resultVariable}_error"] = $e->getMessage();
        }

        $state->forceFill(['variables' => $variables])->save();

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($node['id'] ?? 'httpRequest'),
            nodeType: 'httpRequest',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
            metadata: [
                'url' => $url,
                'method' => $method,
                'success' => (bool) ($variables["{$resultVariable}_success"] ?? false),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $nodeData
     */
    private function processFunctionCall(
        DripCampaignState $state,
        Conversation $conversation,
        array $node,
        array $nodeData,
    ): void {
        $variables = (array) ($state->variables ?? []);
        $functionName = (string) ($nodeData['function_name'] ?? $nodeData['functionName'] ?? $nodeData['function'] ?? '');
        $resultVariable = (string) ($nodeData['resultVariable'] ?? $nodeData['result_variable'] ?? 'function_result');
        $params = is_array($nodeData['parameters'] ?? null) ? $nodeData['parameters'] : [];

        if ($functionName === '') {
            $this->statService->record(
                campaignId: (int) $state->drip_campaign_id,
                nodeId: (string) ($node['id'] ?? 'functionCall'),
                nodeType: 'functionCall',
                action: ChatbotFlowStatAction::Error,
                conversation: $conversation,
                metadata: ['reason' => 'missing_function'],
            );

            return;
        }

        try {
            $result = match ($functionName) {
                'now', 'current_time' => now()->toIso8601String(),
                'contact_name' => $conversation->contact_name,
                'contact_phone' => $conversation->contact_phone,
                'random_number' => random_int((int) ($params['min'] ?? 0), (int) ($params['max'] ?? 100)),
                default => $this->executeRegisteredFunction($functionName, $params, $variables, $conversation),
            };

            $variables[$resultVariable] = $result;
            $variables["{$resultVariable}_success"] = true;
        } catch (\Throwable $e) {
            Log::warning('Drip function call failed', [
                'function' => $functionName,
                'error' => $e->getMessage(),
            ]);
            $variables[$resultVariable] = null;
            $variables["{$resultVariable}_success"] = false;
            $variables["{$resultVariable}_error"] = $e->getMessage();
        }

        $state->forceFill(['variables' => $variables])->save();

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($node['id'] ?? 'functionCall'),
            nodeType: 'functionCall',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
            metadata: ['function' => $functionName],
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $variables
     */
    private function executeRegisteredFunction(
        string $functionName,
        array $params,
        array $variables,
        Conversation $conversation,
    ): mixed {
        $registry = config('chatbot.function_registry', []);
        if (! is_array($registry) || ! isset($registry[$functionName])) {
            return null;
        }

        $className = $registry[$functionName];
        if (! is_string($className) || ! class_exists($className)) {
            return null;
        }

        $instance = app($className);
        if (! method_exists($instance, 'handle')) {
            return null;
        }

        return $instance->handle($params, $variables, $conversation);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $nodeData
     */
    private function processCarouselTemplate(
        DripCampaignState $state,
        Conversation $conversation,
        array $node,
        array $nodeData,
    ): void {
        $variables = (array) ($state->variables ?? []);
        $headerText = $this->variableResolver->resolve(
            (string) ($nodeData['headerText'] ?? $nodeData['text'] ?? $nodeData['message'] ?? ''),
            $variables,
        );
        $cards = $nodeData['cards'] ?? $nodeData['items'] ?? [];

        if ($headerText !== '') {
            $this->outboundService->sendText($conversation, $headerText, enforceWindow: false);
        }

        if (is_array($cards) && $cards !== []) {
            $lines = [];
            foreach ($cards as $i => $card) {
                if (! is_array($card)) {
                    continue;
                }
                $title = (string) ($card['title'] ?? '');
                $subtitle = (string) ($card['subtitle'] ?? $card['description'] ?? '');
                $entry = ($i + 1).'. '.$title;
                if ($subtitle !== '') {
                    $entry .= ' - '.$subtitle;
                }
                $lines[] = $entry;
            }
            if ($lines !== []) {
                $this->outboundService->sendText($conversation, implode("\n", $lines), enforceWindow: false);
            }
        }

        $variables['_carousel_cards'] = $cards;
        $variables['_carousel_node_id'] = (string) ($node['id'] ?? '');
        $state->forceFill(['variables' => $variables])->save();

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($node['id'] ?? 'carouselTemplate'),
            nodeType: 'carouselTemplate',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $nodeData
     */
    private function processWhatsappFlowTemplate(
        DripCampaignState $state,
        Conversation $conversation,
        array $node,
        array $nodeData,
    ): void {
        $variables = (array) ($state->variables ?? []);
        $templateId = $nodeData['templateId'] ?? $nodeData['template_id'] ?? Arr::get($nodeData, 'selectedTemplate.id');

        if ($templateId !== null && $templateId !== '') {
            $template = Template::query()->find($templateId);
            $templateCode = $template?->whatsappCode();
            if (is_string($templateCode) && $templateCode !== '') {
                $this->outboundService->sendTemplate($conversation, $templateCode);
                $variables['_whatsapp_flow_template_id'] = (string) $templateId;
                $variables['_whatsapp_flow_node_id'] = (string) ($node['id'] ?? '');
                $state->forceFill(['variables' => $variables])->save();

                $this->statService->record(
                    campaignId: (int) $state->drip_campaign_id,
                    nodeId: (string) ($node['id'] ?? 'whatsappFlowTemplate'),
                    nodeType: 'whatsappFlowTemplate',
                    action: ChatbotFlowStatAction::Completed,
                    conversation: $conversation,
                );

                return;
            }
        }

        $flowId = (string) ($nodeData['flowId'] ?? $nodeData['flow_id'] ?? '');
        $flowCta = (string) ($nodeData['flowCta'] ?? $nodeData['flow_cta'] ?? $nodeData['ctaText'] ?? 'Open');
        $bodyText = $this->variableResolver->resolve((string) ($nodeData['bodyText'] ?? $nodeData['message'] ?? ''), $variables);
        $flow = $flowId !== '' ? $this->flowInteractiveService->findByIdentifier($flowId) : null;

        if ($flow !== null) {
            $content = $this->flowInteractiveService->buildFlowInteractiveContent(
                $flow,
                $bodyText !== '' ? $bodyText : 'Tap below to continue',
                $flowCta,
                filled($nodeData['flowToken'] ?? $nodeData['flow_token'] ?? null)
                    ? (string) ($nodeData['flowToken'] ?? $nodeData['flow_token'])
                    : null,
            );
            $this->outboundService->sendInteractive($conversation, $content, previewBody: $bodyText ?: null, enforceWindow: false);
            $variables['_whatsapp_flow_id'] = (string) ($content['action']['parameters']['flow_id'] ?? $flowId);
            $variables['_whatsapp_flow_node_id'] = (string) ($node['id'] ?? '');
            $state->forceFill(['variables' => $variables])->save();
        } elseif ($bodyText !== '') {
            $this->outboundService->sendText($conversation, $bodyText, enforceWindow: false);
        }

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: (string) ($node['id'] ?? 'whatsappFlowTemplate'),
            nodeType: 'whatsappFlowTemplate',
            action: ChatbotFlowStatAction::Completed,
            conversation: $conversation,
        );
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    private function evaluateEnhancedCondition(
        DripCampaignState $state,
        Conversation $conversation,
        array $nodeData,
    ): bool {
        $conditions = $nodeData['conditions'] ?? [];
        if (! is_array($conditions) || $conditions === []) {
            return $this->evaluateCondition($state, $conversation, $nodeData);
        }

        $variables = (array) ($state->variables ?? []);
        $contact = $conversation->contact;
        $customFields = (array) ($contact?->custom_fields ?? []);
        $merged = array_merge($customFields, $variables);
        $logicOperator = strtoupper((string) ($nodeData['logicOperator'] ?? $nodeData['logic_operator'] ?? 'AND'));
        $isAnd = $logicOperator !== 'OR';

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }
            $field = (string) ($condition['field'] ?? $condition['variable'] ?? '');
            $operator = (string) ($condition['operator'] ?? 'equals');
            $value = (string) ($condition['value'] ?? '');
            $actual = $contact?->{$field} ?? $merged[$field] ?? null;
            $result = $this->compareValues($actual, $operator, $value);

            if ($isAnd && ! $result) {
                return false;
            }
            if (! $isAnd && $result) {
                return true;
            }
        }

        return $isAnd;
    }

    private function completeState(DripCampaignState $state): void
    {
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Completed,
            'processed_at' => now(),
        ])->save();

        $this->statService->record(
            campaignId: (int) $state->drip_campaign_id,
            nodeId: 'flow',
            nodeType: 'flow',
            action: ChatbotFlowStatAction::Completed,
            conversation: $state->conversation,
        );
    }
}
