<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Http\Requests\StoreAiBotRequest;
use App\Domains\AiBot\Http\Requests\StoreProviderKeyRequest;
use App\Domains\AiBot\Services\AiBotQueryService;
use App\Domains\AiBot\Services\AiBotService;
use App\Domains\AiBot\Services\AiProviderKeyService;
use App\Domains\AiBot\Services\AiTestBotService;
use App\Domains\AiBot\Services\AiTokenUsageService;
use App\Domains\AiBot\Services\KnowledgeBaseProxyService;
use App\Http\Controllers\Controller;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\AiTokenUsageLog;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class OpenAiKeyController extends Controller
{
    private const ALLOWED_TABS = [
        'bot-manager',
        'api-settings',
        'knowledge-base',
        'test-bot',
        'usage-analytics',
        'global-settings',
    ];

    public function __construct(
        private readonly AiBotQueryService $queryService,
        private readonly AiBotService $botService,
        private readonly AiProviderKeyService $keyService,
        private readonly AiTokenUsageService $tokenUsageService,
        private readonly AiTestBotService $testBotService,
        private readonly KnowledgeBaseProxyService $knowledgeBaseProxy,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'bot-manager');

        if (! in_array($tab, self::ALLOWED_TABS, true)) {
            abort(404);
        }

        $data = ['tab' => $tab];

        match ($tab) {
            'bot-manager' => $this->loadBotManagerData($request, $data),
            'api-settings' => $this->loadApiSettingsData($data),
            'knowledge-base' => $this->loadKnowledgeBaseData($request, $data),
            'test-bot' => $this->loadTestBotData($data),
            'usage-analytics' => $this->loadUsageAnalyticsData($data),
            'global-settings' => $this->loadGlobalSettingsData($data),
        };

        return view('openai-key.index', $data);
    }

    // --- Bot Manager Actions ---

    public function storeBot(StoreAiBotRequest $request): RedirectResponse
    {
        $this->botService->create($request->validated());

        return redirect()
            ->route('openai-key.index', ['tab' => 'bot-manager'])
            ->with('status', 'AI bot created successfully.');
    }

    public function toggleDefault(AiBot $aiBot): JsonResponse
    {
        $this->botService->toggleDefault($aiBot);

        $aiBot->refresh();

        return response()->json([
            'is_default' => $aiBot->is_default,
        ]);
    }

    public function destroyBot(AiBot $aiBot): RedirectResponse
    {
        $this->botService->delete($aiBot);

        return redirect()
            ->route('openai-key.index', ['tab' => 'bot-manager'])
            ->with('status', 'AI bot deleted successfully.');
    }

    // --- API Settings Actions ---

    public function storeProviderKey(StoreProviderKeyRequest $request): RedirectResponse
    {
        $this->keyService->create($request->validated());

        return redirect()
            ->route('openai-key.index', ['tab' => 'api-settings'])
            ->with('status', 'Provider key added successfully.');
    }

    public function validateProviderKey(AiProviderKey $provider_key): JsonResponse
    {
        $isValid = $this->keyService->validateKey($provider_key);

        return response()->json([
            'is_validated' => $isValid,
        ]);
    }

    public function destroyProviderKey(AiProviderKey $provider_key): RedirectResponse
    {
        $this->keyService->delete($provider_key);

        return redirect()
            ->route('openai-key.index', ['tab' => 'api-settings'])
            ->with('status', 'Provider key deleted successfully.');
    }

    /**
     * List chat/embedding models for a provider (legacy parity).
     * Uses pasted api_key, else the tenant's active key for that provider.
     */
    public function listProviderModels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:openai,gemini,azure'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $provider = (string) $validated['provider'];
        $apiKey = filled($validated['api_key'] ?? null) ? (string) $validated['api_key'] : null;

        if ($apiKey === null) {
            $keyRow = AiProviderKey::query()
                ->where('provider', $provider)
                ->where('is_active', true)
                ->orderByDesc('is_validated')
                ->orderByDesc('id')
                ->first();

            if ($keyRow === null || blank($keyRow->api_key)) {
                return response()->json([
                    'error' => 'No active API key for this provider. Paste a key or save one first.',
                    'chat_models' => [],
                    'embedding_models' => [],
                ], 404);
            }

            $apiKey = (string) $keyRow->api_key;
        }

        try {
            $models = $this->keyService->listModels($provider, $apiKey);

            return response()->json([
                'success' => true,
                'chat_models' => $models['chat_models'],
                'embedding_models' => $models['embedding_models'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'chat_models' => [],
                'embedding_models' => [],
            ], 502);
        }
    }

    // --- Knowledge Base Actions (moved to KnowledgeBaseController / Chroma proxy) ---

    // --- Test Bot Action ---

    public function testBot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_id' => PublicId::uuidExistsRules(AiBot::class, nullable: false),
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $bot = PublicId::findOrFail(AiBot::class, (string) $validated['bot_id']);

        try {
            $result = $this->testBotService->test($bot, $validated['message']);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // --- Settings + Usage Actions ---

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_auto_response_enabled' => ['nullable', 'boolean'],
        ]);

        AiSetting::set('ai_auto_response_enabled', (bool) ($validated['ai_auto_response_enabled'] ?? false));

        return redirect()
            ->route('openai-key.index', ['tab' => 'global-settings'])
            ->with('status', 'Settings saved successfully.');
    }

    public function clearUsage(): RedirectResponse
    {
        AiTokenUsageLog::query()->delete();

        return redirect()
            ->route('openai-key.index', ['tab' => 'usage-analytics'])
            ->with('status', 'Usage data cleared successfully.');
    }

    // --- Tab Data Loaders ---

    private function loadBotManagerData(Request $request, array &$data): void
    {
        $data['bots'] = $this->queryService->paginate(
            perPage: 10,
            search: $request->query('search'),
        )->withQueryString();
    }

    private function loadApiSettingsData(array &$data): void
    {
        $data['keys'] = AiProviderKey::query()
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    private function loadKnowledgeBaseData(Request $request, array &$data): void
    {
        $bots = $this->queryService->activeBots();
        $data['bots'] = $bots;

        $selectedBotId = $request->query('bot');
        $selectedBot = $selectedBotId
            ? $bots->firstWhere('uuid', (string) $selectedBotId)
            : $bots->first();

        $data['selectedBot'] = $selectedBot;
        $data['kbError'] = null;
        $data['kbDocuments'] = [];
        $data['kbTotal'] = 0;
        $data['kbLimit'] = max(1, min((int) $request->query('limit', 10), 50));
        $data['kbOffset'] = max(0, (int) $request->query('offset', 0));
        $data['storageInfo'] = [
            'document_count' => 0,
            'total_size_mb' => 0,
            'file_types' => [],
        ];

        if ($selectedBot === null) {
            return;
        }

        $data['kbChromaClientId'] = $this->knowledgeBaseProxy->chromaClientId();
        $data['kbChromaBotId'] = $this->knowledgeBaseProxy->chromaBotId($selectedBot);

        try {
            $list = $this->knowledgeBaseProxy->list(
                botId: $selectedBot->uuid,
                limit: $data['kbLimit'],
                offset: $data['kbOffset'],
            );
            $listData = is_array($list['data'] ?? null) ? $list['data'] : $list;
            $data['kbDocuments'] = is_array($listData['documents'] ?? null) ? $listData['documents'] : [];
            $data['kbTotal'] = (int) ($listData['total_documents'] ?? count($data['kbDocuments']));
            $data['kbCollectionName'] = $listData['collection_name'] ?? null;

            $storage = $this->knowledgeBaseProxy->storageInfo(botId: $selectedBot->uuid);
            $storageData = is_array($storage['data'] ?? null) ? $storage['data'] : $storage;
            $data['storageInfo'] = [
                'document_count' => (int) ($storageData['document_count'] ?? $data['kbTotal']),
                'total_size_mb' => (float) ($storageData['total_size_mb'] ?? 0),
                'file_types' => is_array($storageData['file_types'] ?? null) ? $storageData['file_types'] : [],
            ];
        } catch (Throwable $e) {
            $data['kbError'] = $e->getMessage();
        }
    }

    private function loadTestBotData(array &$data): void
    {
        $data['bots'] = $this->queryService->activeBots();
    }

    private function loadUsageAnalyticsData(array &$data): void
    {
        $data['stats'] = $this->tokenUsageService->globalStats();
        $data['storageInfo'] = [
            'document_count' => 0,
            'total_size_mb' => 0,
            'file_types' => [],
        ];
        $data['fileTypes'] = [];

        try {
            $storage = $this->knowledgeBaseProxy->storageInfo();
            $storageData = is_array($storage['data'] ?? null) ? $storage['data'] : $storage;
            $data['storageInfo'] = [
                'document_count' => (int) ($storageData['document_count'] ?? 0),
                'total_size_mb' => (float) ($storageData['total_size_mb'] ?? 0),
                'file_types' => is_array($storageData['file_types'] ?? null) ? $storageData['file_types'] : [],
            ];
            $types = $data['storageInfo']['file_types'];
            $data['fileTypes'] = array_combine($types, array_fill(0, count($types), 1)) ?: [];
        } catch (Throwable) {
            // Usage tab still shows token stats if Chroma is down.
        }
    }

    private function loadGlobalSettingsData(array &$data): void
    {
        $data['autoResponseEnabled'] = AiSetting::getBool('ai_auto_response_enabled', false);
    }
}
