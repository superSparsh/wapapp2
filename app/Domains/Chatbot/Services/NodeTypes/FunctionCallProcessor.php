<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class FunctionCallProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variables = $state->variables ?? [];

        $functionName = (string) ($data['functionName'] ?? $data['function'] ?? '');
        $resultVariable = (string) ($data['resultVariable'] ?? 'function_result');

        if ($functionName === '') {
            return NodeProcessResult::Error;
        }

        try {
            $result = $this->executeFunction($functionName, $data, $variables, $conversation);

            $state->mergeVariables([
                $resultVariable => $result,
                "{$resultVariable}_success" => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chatbot function call failed', [
                'function' => $functionName,
                'error' => $e->getMessage(),
            ]);

            $state->mergeVariables([
                $resultVariable => null,
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
     * Execute a named function. Functions are resolved from a registry
     * or via callable class name for extensibility.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $variables
     */
    private function executeFunction(string $functionName, array $data, array $variables, Conversation $conversation): mixed
    {
        $params = $data['parameters'] ?? [];

        // Built-in functions
        return match ($functionName) {
            'now', 'current_time' => now()->toIso8601String(),
            'contact_name' => $conversation->contact_name,
            'contact_phone' => $conversation->contact_phone,
            'random_number' => random_int((int) ($params['min'] ?? 0), (int) ($params['max'] ?? 100)),
            default => $this->executeCallableFunction($functionName, $params, $variables, $conversation),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $variables
     */
    private function executeCallableFunction(string $functionName, array $params, array $variables, Conversation $conversation): mixed
    {
        // Allow registered callable classes
        $registry = config('chatbot.function_registry', []);

        if (is_array($registry) && isset($registry[$functionName])) {
            $className = $registry[$functionName];

            if (class_exists($className)) {
                $instance = app($className);

                if (method_exists($instance, 'handle')) {
                    return $instance->handle($params, $variables, $conversation);
                }
            }
        }

        Log::info('Chatbot function call: no handler registered', ['function' => $functionName]);

        return null;
    }
}
