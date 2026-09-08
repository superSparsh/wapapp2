<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Models\Message;
use App\Models\WhatsappFlow;
use Illuminate\Support\Arr;

class WhatsappFlowInboundService
{
    public function __construct(
        private readonly WhatsappFlowQueryService $queryService,
        private readonly FlowDataExchangeService $exchangeService,
    ) {}

    public function handleInteractiveMessage(Message $message): void
    {
        $metadata = $message->metadata ?? [];
        $interactive = is_array($metadata['interactive'] ?? null)
            ? $metadata['interactive']
            : (is_array($metadata['raw'] ?? null) ? $metadata['raw'] : []);

        if ($interactive === []) {
            return;
        }

        $responseJson = Arr::get($interactive, 'nfm_reply.response_json');

        if (! is_string($responseJson) || $responseJson === '') {
            return;
        }

        $flowToken = Arr::get($interactive, 'nfm_reply.flow_token');
        $metaFlowId = Arr::get($interactive, 'nfm_reply.name');

        $flow = $this->resolveFlow($flowToken, $metaFlowId);

        if ($flow === null) {
            return;
        }

        $conversation = $message->conversation;
        $phone = (string) ($conversation?->contact_phone ?? '');

        $this->exchangeService->ingestInboundCompletion(
            flow: $flow,
            contactPhone: $phone,
            interactivePayload: $interactive,
            conversationId: $conversation?->id,
        );
    }

    private function resolveFlow(mixed $flowToken, mixed $metaFlowId): ?WhatsappFlow
    {
        if (is_string($flowToken) && $flowToken !== '') {
            $byToken = $this->queryService->findByDataExchangeToken($flowToken);

            if ($byToken !== null) {
                return $byToken;
            }
        }

        if (is_string($metaFlowId) && $metaFlowId !== '') {
            return $this->queryService->findByMetaFlowId($metaFlowId);
        }

        return null;
    }
}
