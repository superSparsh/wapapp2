<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Services\KnowledgeBaseProxyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;

/**
 * Proxy-only Knowledge Base endpoints (legacy parity).
 * Chroma via Python AI service is the source of truth — nothing is mirrored to MySQL.
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseProxyService $kb,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $payload = $this->kb->list(
                botId: $request->query('bot_id') ?? $request->query('bot'),
                limit: (int) $request->query('limit', 10),
                offset: (int) $request->query('offset', 0),
            );

            return response()->json([
                'success' => true,
                'data' => $payload['data'] ?? $payload,
            ]);
        });
    }

    public function storageInfo(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $payload = $this->kb->storageInfo(
                botId: $request->query('bot_id') ?? $request->query('bot'),
            );

            return response()->json([
                'success' => true,
                'data' => $payload['data'] ?? $payload,
            ]);
        });
    }

    public function download(Request $request): Response|JsonResponse
    {
        try {
            $payload = $this->kb->download(
                botId: $request->query('bot_id') ?? $request->query('bot'),
            );

            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return response($json ?: '{}', 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="knowledge-base.json"',
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function clear(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        try {
            $botId = $request->input('bot_id') ?? $request->query('bot_id') ?? $request->query('bot');
            $payload = $this->kb->clear(botId: $botId);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json($payload);
            }

            return redirect()
                ->route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $botId])
                ->with('status', 'Knowledge Base cleared from Chroma.');
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return $this->errorResponse($e);
            }

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function addManualContent(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $validated = $request->validate([
                'documents' => ['required', 'array', 'min:1'],
                'documents.*' => ['required', 'string'],
                'metadata' => ['nullable', 'array'],
                'bot_id' => ['nullable', 'string'],
            ]);

            $payload = $this->kb->addManualContent(
                documents: $validated['documents'],
                metadata: $validated['metadata'] ?? [],
                botId: $validated['bot_id'] ?? null,
            );

            return response()->json($payload);
        });
    }

    public function uploadFile(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $validated = $request->validate([
                'file' => ['required', 'file', 'max:10240'],
                'bot_id' => ['nullable', 'string'],
                'metadata' => ['nullable'],
            ]);

            $metadata = $validated['metadata'] ?? [];
            if (is_string($metadata)) {
                $decoded = json_decode($metadata, true);
                $metadata = is_array($decoded) ? $decoded : [];
            }

            $payload = $this->kb->uploadFile(
                file: $request->file('file'),
                metadata: is_array($metadata) ? $metadata : [],
                botId: $validated['bot_id'] ?? null,
            );

            return response()->json([
                'message' => 'File uploaded successfully',
                'data' => $payload,
            ]);
        });
    }

    public function scrapeWebsite(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $validated = $request->validate([
                'url' => ['required', 'url', 'max:2048'],
                'max_pages' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $payload = $this->kb->scrapeWebsite(
                url: $validated['url'],
                maxPages: (int) ($validated['max_pages'] ?? 15),
            );

            return response()->json($payload);
        });
    }

    public function extractText(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $request->validate([
                'file' => ['required', 'file', 'max:10240'],
            ]);

            $payload = $this->kb->extractTextFromFile($request->file('file'));

            return response()->json($payload);
        });
    }

    public function structureText(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $validated = $request->validate([
                'text' => ['required', 'string'],
                'bot_id' => ['nullable', 'string'],
            ]);

            $payload = $this->kb->structureText(
                text: $validated['text'],
                botId: $validated['bot_id'] ?? null,
            );

            return response()->json($payload);
        });
    }

    public function reindex(Request $request, string $aiBot): JsonResponse
    {
        return $this->wrap(function () use ($aiBot) {
            $payload = $this->kb->reindex(botId: $aiBot);

            return response()->json($payload);
        });
    }

    public function processQuery(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request) {
            $validated = $request->validate([
                'query_text' => ['required', 'string', 'max:4000'],
                'bot_id' => ['nullable', 'string'],
                'chat_history' => ['nullable', 'array'],
            ]);

            $payload = $this->kb->processQuery(
                queryText: $validated['query_text'],
                botId: $validated['bot_id'] ?? null,
                chatHistory: $validated['chat_history'] ?? [],
            );

            return response()->json([
                'response' => $payload['response'] ?? $payload,
                'metadata' => $payload['metadata'] ?? null,
            ]);
        });
    }

    /**
     * @param  callable(): JsonResponse  $callback
     */
    private function wrap(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(Throwable $e): JsonResponse
    {
        $status = $e instanceof RuntimeException && $e->getCode() >= 400 && $e->getCode() < 600
            ? (int) $e->getCode()
            : 500;

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], $status >= 400 ? $status : 500);
    }
}
