<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Models\WhatsappFlow;
use Illuminate\Support\Str;

class WhatsappFlowInteractiveService
{
    public function findByIdentifier(string $identifier): ?WhatsappFlow
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return WhatsappFlow::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('meta_flow_id', $identifier)
                    ->orWhere('uuid', $identifier);

                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }

    public function generateFlowToken(string $flowId): string
    {
        return $flowId.'_'.Str::uuid();
    }

    public function firstScreenId(WhatsappFlow $flow): ?string
    {
        $metaJson = is_array($flow->meta_json) ? $flow->meta_json : [];
        $internalJson = is_array($flow->flow_json) ? $flow->flow_json : [];

        $screenId = $metaJson['screens'][0]['id'] ?? $internalJson['screens'][0]['id'] ?? $internalJson['first_screen'] ?? null;

        return is_string($screenId) && $screenId !== '' ? $screenId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFlowInteractiveContent(
        WhatsappFlow $flow,
        string $bodyText,
        string $flowCta,
        ?string $flowToken = null,
    ): array {
        $metaFlowId = (string) ($flow->meta_flow_id ?? $flow->id);
        $firstScreen = $this->firstScreenId($flow) ?? 'SCREEN_1';
        $token = $flowToken ?: $this->generateFlowToken($metaFlowId);

        return [
            'type' => 'flow',
            'body' => ['text' => $bodyText],
            'action' => [
                'name' => 'flow',
                'parameters' => [
                    'mode' => 'published',
                    'flow_message_version' => '3',
                    'flow_token' => $token,
                    'flow_id' => $metaFlowId,
                    'flow_cta' => $flowCta,
                    'flow_action' => 'navigate',
                    'flow_action_payload' => [
                        'screen' => $firstScreen,
                        'data' => new \stdClass,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function legacyFlowList(): array
    {
        return WhatsappFlow::query()
            ->active()
            ->whereNotNull('meta_flow_id')
            ->whereNotNull('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (WhatsappFlow $flow): array {
                $metaFlowId = (string) ($flow->meta_flow_id ?? $flow->id);

                return [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'status' => $flow->status->value,
                    'meta_flow_id' => $flow->meta_flow_id,
                    'flowId' => $metaFlowId,
                    'flowName' => $flow->name,
                    'categories' => (array) ($flow->categories ?? []),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templatePickerOptions(): array
    {
        return WhatsappFlow::query()
            ->active()
            ->orderByDesc('id')
            ->get()
            ->map(fn (WhatsappFlow $flow): array => [
                'id' => (string) $flow->id,
                'name' => $flow->name,
                'meta_flow_id' => (string) ($flow->meta_flow_id ?? ''),
                'first_screen' => $this->firstScreenId($flow) ?? '',
                'is_ready' => filled($flow->meta_flow_id) && $this->firstScreenId($flow) !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{flow_id: string, navigate_screen: string}|null
     */
    public function resolveTemplateButtonFlow(string $internalOrMetaId): ?array
    {
        $flow = $this->findByIdentifier($internalOrMetaId);

        if ($flow === null) {
            return null;
        }

        $metaFlowId = (string) ($flow->meta_flow_id ?? '');

        if ($metaFlowId === '') {
            return null;
        }

        $screen = $this->firstScreenId($flow);

        if ($screen === null) {
            return null;
        }

        return [
            'flow_id' => $metaFlowId,
            'navigate_screen' => $screen,
        ];
    }
}
