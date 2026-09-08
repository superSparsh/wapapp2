<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Domains\Chatbot\Support\FlowCacheManager;
use App\Domains\Chatbot\Support\FlowNodeDataMapper;
use App\Enums\ChatbotFlowStatus;
use App\Models\ChatbotFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatbotFlowService
{
    public function __construct(
        private readonly FlowDataNormalizer $normalizer,
        private readonly FlowCacheManager $cacheManager,
        private readonly FlowNodeDataMapper $nodeDataMapper,
    ) {}

    /**
     * @param  array{name: string, status?: string, whatsapp_line_id?: int|null, created_by?: int|null}  $data
     */
    public function create(array $data): ChatbotFlow
    {
        $maxFlows = (int) config('chatbot.max_flows_per_tenant', 50);

        if (ChatbotFlow::query()->count() >= $maxFlows) {
            throw ValidationException::withMessages([
                'name' => "You can create up to {$maxFlows} chatbot flows.",
            ]);
        }

        return DB::transaction(function () use ($data): ChatbotFlow {
            return ChatbotFlow::query()->create([
                'name' => $data['name'],
                'status' => ChatbotFlowStatus::Draft,
                'whatsapp_line_id' => $data['whatsapp_line_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{name?: string, status?: string, whatsapp_line_id?: int|null}  $data
     */
    public function update(ChatbotFlow $flow, array $data): ChatbotFlow
    {
        return DB::transaction(function () use ($flow, $data): ChatbotFlow {
            $fillable = [];

            if (isset($data['name'])) {
                $fillable['name'] = $data['name'];
            }

            if (isset($data['status'])) {
                $fillable['status'] = ChatbotFlowStatus::from($data['status']);
            }

            if (array_key_exists('whatsapp_line_id', $data)) {
                $fillable['whatsapp_line_id'] = $data['whatsapp_line_id'];
            }

            if ($fillable !== []) {
                $flow->update($fillable);
            }

            return $flow->refresh();
        });
    }

    public function delete(ChatbotFlow $flow): void
    {
        DB::transaction(function () use ($flow): void {
            $this->cacheManager->forgetNodeMap($flow->id);
            $flow->delete();
        });
    }

    public function toggle(ChatbotFlow $flow): ChatbotFlow
    {
        if (! $flow->isActive()) {
            $this->assertActivatable($flow);
        }

        $newStatus = $flow->isActive()
            ? ChatbotFlowStatus::Inactive
            : ChatbotFlowStatus::Active;

        $flow->update(['status' => $newStatus]);

        if (! $flow->isActive()) {
            $this->cacheManager->forgetNodeMap($flow->id);
        }

        return $flow->refresh();
    }

    public function publish(ChatbotFlow $flow): ChatbotFlow
    {
        $this->assertActivatable($flow);

        $flow->update([
            'status' => ChatbotFlowStatus::Active,
            'published_at' => now(),
        ]);

        return $flow->refresh();
    }

    public function duplicate(ChatbotFlow $flow): ChatbotFlow
    {
        return DB::transaction(function () use ($flow): ChatbotFlow {
            $clone = $flow->replicate(['uuid', 'published_at']);
            $clone->name = $flow->name.' (Copy)';
            $clone->status = ChatbotFlowStatus::Draft;
            $clone->save();

            return $clone;
        });
    }

    public function clearCache(ChatbotFlow $flow): void
    {
        $this->normalizer->bustCache($flow->id);
    }

    /**
     * Save legacy React Flow payload without field remapping.
     *
     * @param  array<string, mixed>  $flowData
     */
    public function saveLegacyFlowData(ChatbotFlow $flow, array $flowData): ChatbotFlow
    {
        $flow->update(['exported_data' => $flowData]);
        $this->normalizer->bustCache($flow->id);

        return $flow->refresh();
    }

    /**
     * Save the flow's exported_data (the ReactFlow/Drawflow node map).
     *
     * @param  array<string, mixed>  $flowData
     */
    public function saveFlowData(ChatbotFlow $flow, array $flowData): ChatbotFlow
    {
        $normalized = $this->nodeDataMapper->prepareForStorage($flowData);
        $maxNodes = (int) config('chatbot.max_nodes_per_flow', 100);

        if (count($normalized['nodes']) > $maxNodes) {
            throw ValidationException::withMessages([
                'nodes' => "A flow can have at most {$maxNodes} nodes.",
            ]);
        }

        $flow->update(['exported_data' => $normalized]);
        $this->normalizer->bustCache($flow->id);

        return $flow->refresh();
    }

    /**
     * @return array<int, string>
     */
    public function activationErrors(ChatbotFlow $flow): array
    {
        $errors = [];

        if (! $flow->hasFlowData()) {
            $errors[] = 'Add at least one node before activating this flow.';

            return $errors;
        }

        $data = $flow->exported_data;
        $nodes = is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
        $hasTrigger = false;

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = (string) ($node['type'] ?? '');
            $nodeData = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (! in_array($type, ['welcomeMessage', 'templateMessage'], true)) {
                continue;
            }

            $keyword = trim((string) ($nodeData['triggerKeyword'] ?? $nodeData['keywords'] ?? ''));

            if ($keyword !== '') {
                $hasTrigger = true;
                break;
            }
        }

        if (! $hasTrigger) {
            $errors[] = 'Add a welcome or template node with trigger keywords before activating.';
        }

        return $errors;
    }

    private function assertActivatable(ChatbotFlow $flow): void
    {
        $errors = $this->activationErrors($flow);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'flow' => $errors[0],
            ]);
        }
    }
}
