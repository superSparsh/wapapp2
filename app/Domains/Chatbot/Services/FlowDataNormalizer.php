<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Domains\Chatbot\Support\FlowCacheManager;
use App\Models\ChatbotFlow;

class FlowDataNormalizer
{
    public function __construct(
        private readonly FlowCacheManager $cacheManager,
    ) {}

    /**
     * Get the normalized node map for a flow (cache-first).
     *
     * @return array<string, array{id: string, type: string, data: array<string, mixed>, outputs: array<string, array{connections: array<int, array{node: string}>}>}>
     */
    public function normalize(ChatbotFlow $flow): array
    {
        $cached = $this->cacheManager->getNodeMap($flow->id);

        if ($cached !== null) {
            return $cached;
        }

        $data = $flow->exported_data;

        if (! is_array($data)) {
            return [];
        }

        $nodeMap = $this->buildNodeMap($data);
        $this->cacheManager->putNodeMap($flow->id, $nodeMap);

        return $nodeMap;
    }

    /**
     * Bust the cache for a flow (called on save/publish).
     */
    public function bustCache(int $flowId): void
    {
        $this->cacheManager->forgetNodeMap($flowId);
    }

    /**
     * Build a normalized node map from either ReactFlow or legacy Drawflow format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    private function buildNodeMap(array $data): array
    {
        // ReactFlow format: {nodes: [...], edges: [...]}
        if (isset($data['nodes']) && is_array($data['nodes'])) {
            return $this->fromReactFlow($data['nodes'], $data['edges'] ?? []);
        }

        // Legacy Drawflow format: {drawflow: {Home: {data: {...}}}}
        if (isset($data['drawflow']['Home']['data']) && is_array($data['drawflow']['Home']['data'])) {
            return $data['drawflow']['Home']['data'];
        }

        return [];
    }

    /**
     * Convert ReactFlow nodes + edges to the legacy-style normalized map.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array<string, mixed>>
     */
    private function fromReactFlow(array $nodes, array $edges): array
    {
        $edgeIndex = $this->indexEdges($edges);
        $map = [];

        foreach ($nodes as $node) {
            $nodeId = (string) ($node['id'] ?? '');

            if ($nodeId === '') {
                continue;
            }

            $map[$nodeId] = [
                'id' => $nodeId,
                'class' => $node['type'] ?? 'unknown',
                'data' => $node['data'] ?? [],
                'outputs' => $this->buildOutputs($nodeId, $edgeIndex),
            ];
        }

        return $map;
    }

    /**
     * Index edges by source node ID for O(1) lookup.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function indexEdges(array $edges): array
    {
        $index = [];

        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }

            $source = (string) ($edge['source'] ?? '');

            if ($source === '') {
                continue;
            }

            $index[$source][] = $edge;
        }

        return $index;
    }

    /**
     * Build outputs map for a node from indexed edges.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $edgeIndex
     * @return array<string, array{connections: array<int, array{node: string}>}>
     */
    private function buildOutputs(string $nodeId, array $edgeIndex): array
    {
        $edges = $edgeIndex[$nodeId] ?? [];
        $outputs = [];

        foreach ($edges as $edge) {
            $handle = (string) ($edge['sourceHandle'] ?? 'output_1');

            if (! isset($outputs[$handle])) {
                $outputs[$handle] = ['connections' => []];
            }

            $target = (string) ($edge['target'] ?? '');

            if ($target !== '') {
                $outputs[$handle]['connections'][] = ['node' => $target];
            }
        }

        if ($outputs === []) {
            $outputs['output_1'] = ['connections' => []];
        }

        return $outputs;
    }
}
