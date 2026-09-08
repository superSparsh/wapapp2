<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpRequestProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variables = $state->variables ?? [];

        $url = $this->resolveText((string) ($data['url'] ?? ''), $variables);
        $method = strtoupper((string) ($data['method'] ?? 'GET'));
        $headers = $this->resolveHeaders($data['headers'] ?? [], $variables);
        $body = $this->resolveBody($data['body'] ?? null, $variables);
        $resultVariable = (string) ($data['resultVariable'] ?? 'http_response');

        if ($url === '') {
            return NodeProcessResult::Error;
        }

        try {
            $response = $this->executeRequest($method, $url, $headers, $body);

            $state->mergeVariables([
                $resultVariable => $response['body'],
                "{$resultVariable}_status" => $response['status'],
                "{$resultVariable}_success" => $response['success'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chatbot HTTP request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            $state->mergeVariables([
                $resultVariable => null,
                "{$resultVariable}_status" => 0,
                "{$resultVariable}_success" => false,
                "{$resultVariable}_error" => $e->getMessage(),
            ]);
        }

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return NodeProcessResult::Continue;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    private function resolveHeaders(mixed $headers, array $variables): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $resolved = [];

        foreach ($headers as $key => $value) {
            $resolved[$this->resolveText((string) $key, $variables)] = $this->resolveText((string) $value, $variables);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function resolveBody(mixed $body, array $variables): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_array($body)) {
            $jsonBody = json_encode($body);

            return $jsonBody !== false ? $this->resolveText($jsonBody, $variables) : null;
        }

        return $this->resolveText((string) $body, $variables);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{status: int, body: string|null, success: bool}
     */
    private function executeRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $pending = Http::timeout(15)->withHeaders($headers);

        $response = match ($method) {
            'POST' => $pending->withBody($body ?? '', 'application/json')->post($url),
            'PUT' => $pending->withBody($body ?? '', 'application/json')->put($url),
            'PATCH' => $pending->withBody($body ?? '', 'application/json')->patch($url),
            'DELETE' => $pending->delete($url),
            default => $pending->get($url),
        };

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'success' => $response->successful(),
        ];
    }
}
