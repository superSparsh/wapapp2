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
     * Get the normalized node map for a flow (cache-first, versioned).
     *
     * @return array<string, array{id: string, type: string, data: array<string, mixed>, outputs: array<string, array{connections: array<int, array{node: string}>}>}>
     */
    public function normalize(ChatbotFlow $flow, bool $bypassCache = false): array
    {
        $data = $flow->exported_data;

        if ($bypassCache) {
            return is_array($data) ? $this->buildNodeMap($data) : [];
        }

        $version = $this->cacheManager->versionFor(
            $flow->updated_at,
            is_array($data) ? $data : null,
        );

        $cached = $this->cacheManager->getNodeMap($flow->id, $version);

        if ($cached !== null) {
            return $cached;
        }

        if (! is_array($data)) {
            return [];
        }

        $nodeMap = $this->buildNodeMap($data);
        $this->cacheManager->putNodeMap($flow->id, $nodeMap, $version);

        return $nodeMap;
    }

    /**
     * Bust the cache for a flow (called on save/publish).
     */
    public function bustCache(int $flowId, string $version = ''): void
    {
        $this->cacheManager->forgetNodeMap($flowId, $version);
    }

    /**
     * Bust any prior cache entry and warm the versioned map for this flow.
     */
    public function refreshCache(ChatbotFlow $flow): array
    {
        $data = $flow->exported_data;
        $version = $this->cacheManager->versionFor(
            $flow->updated_at,
            is_array($data) ? $data : null,
        );

        // Drop legacy + previous version keys when we know the new version.
        $this->cacheManager->forgetNodeMap($flow->id, $version);
        $this->cacheManager->forgetNodeMap($flow->id, '');

        if (! is_array($data)) {
            return [];
        }

        $nodeMap = $this->buildNodeMap($data);
        $this->cacheManager->putNodeMap($flow->id, $nodeMap, $version);

        return $nodeMap;
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
        $edges = $this->normalizeDateTimeConditionEdges($nodes, $edges);
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
     * React Flow Loose mode + onConnect often save BH edges as null/"default".
     * Remap unlabeled outbound edges to open then closed so branching works.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDateTimeConditionEdges(array $nodes, array $edges): array
    {
        $bhIds = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = (string) ($node['id'] ?? '');
            $type = (string) ($node['type'] ?? '');
            if ($id !== '' && $type === 'dateTimeCondition') {
                $bhIds[$id] = true;
            }
        }

        if ($bhIds === []) {
            return $edges;
        }

        $namedOpen = ['open', 'output_open', 'yes', 'output_yes', 'true', 'output_true'];
        $namedClosed = ['closed', 'output_closed', 'no', 'output_no', 'false', 'output_false'];

        /** @var array<string, list<int>> $indexesBySource */
        $indexesBySource = [];
        foreach ($edges as $index => $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $source = (string) ($edge['source'] ?? '');
            if ($source === '' || ! isset($bhIds[$source])) {
                continue;
            }
            $indexesBySource[$source][] = $index;
        }

        foreach ($indexesBySource as $indexes) {
            $hasOpen = false;
            $hasClosed = false;
            $unlabeled = [];

            foreach ($indexes as $index) {
                $handle = strtolower(trim((string) ($edges[$index]['sourceHandle'] ?? '')));
                if (in_array($handle, $namedOpen, true)) {
                    $hasOpen = true;
                    $edges[$index]['sourceHandle'] = 'open';
                } elseif (in_array($handle, $namedClosed, true)) {
                    $hasClosed = true;
                    $edges[$index]['sourceHandle'] = 'closed';
                } else {
                    $unlabeled[] = $index;
                }
            }

            foreach ($unlabeled as $index) {
                if (! $hasOpen) {
                    $edges[$index]['sourceHandle'] = 'open';
                    $hasOpen = true;
                } elseif (! $hasClosed) {
                    $edges[$index]['sourceHandle'] = 'closed';
                    $hasClosed = true;
                } else {
                    $edges[$index]['sourceHandle'] = 'open';
                }
            }
        }

        return array_values($edges);
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
        $anonymousIndex = 1;

        foreach ($edges as $edge) {
            $rawHandle = $edge['sourceHandle'] ?? null;
            $handle = is_string($rawHandle) ? trim($rawHandle) : '';

            // React Flow often stores null/"" when the connection was not made
            // from a named handle — assign output_1, output_2, … in edge order.
            if ($handle === '' || strtolower($handle) === 'null') {
                while (isset($outputs['output_'.$anonymousIndex])) {
                    $anonymousIndex++;
                }
                $handle = 'output_'.$anonymousIndex;
                $anonymousIndex++;
            }

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
