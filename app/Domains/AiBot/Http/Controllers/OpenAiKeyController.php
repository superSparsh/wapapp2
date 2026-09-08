<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Http\Requests\StoreAiBotRequest;
use App\Domains\AiBot\Http\Requests\StoreBusinessInfoRequest;
use App\Domains\AiBot\Http\Requests\StoreProviderKeyRequest;
use App\Domains\AiBot\Services\AiBotQueryService;
use App\Domains\AiBot\Services\AiBotService;
use App\Domains\AiBot\Services\AiBusinessInfoService;
use App\Domains\AiBot\Services\AiProviderKeyService;
use App\Domains\AiBot\Services\AiTestBotService;
use App\Domains\AiBot\Services\AiTokenUsageService;
use App\Http\Controllers\Controller;
use App\Models\AiBot;
use App\Models\AiBusinessInfo;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\AiTokenUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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
        private readonly AiBusinessInfoService $businessInfoService,
        private readonly AiTokenUsageService $tokenUsageService,
        private readonly AiTestBotService $testBotService,
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

    // --- Knowledge Base Actions ---

    public function storeBusinessInfo(StoreBusinessInfoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bot = AiBot::query()->findOrFail($validated['ai_bot_id']);

        if ($request->hasFile('file')) {
            $this->businessInfoService->upload($bot, $request->file('file'), $validated['title']);
        } else {
            $this->businessInfoService->create($bot, $validated);
        }

        return redirect()
            ->route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $bot->id])
            ->with('status', 'Knowledge base entry added successfully.');
    }

    public function destroyBusinessInfo(AiBot $aiBot, AiBusinessInfo $business_info): RedirectResponse
    {
        $this->businessInfoService->delete($business_info);

        return redirect()
            ->route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $aiBot->id])
            ->with('status', 'Knowledge base entry deleted successfully.');
    }

    // --- Test Bot Action ---

    public function testBot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_id' => ['required', 'integer', 'exists:ai_bots,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $bot = AiBot::query()->find($validated['bot_id']);

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
            ? $bots->firstWhere('id', (int) $selectedBotId)
            : $bots->first();

        $data['selectedBot'] = $selectedBot;

        $data['entries'] = $selectedBot
            ? $selectedBot->businessInfoEntries()->orderByDesc('created_at')->paginate(10)
            : collect();

        $data['storageInfo'] = $this->calculateStorageInfo($selectedBot);
    }

    private function loadTestBotData(array &$data): void
    {
        $data['bots'] = $this->queryService->activeBots();
    }

    private function loadUsageAnalyticsData(array &$data): void
    {
        $data['stats'] = $this->tokenUsageService->globalStats();

        $data['storageInfo'] = $this->calculateStorageInfo();

        $data['fileTypes'] = AiBusinessInfo::query()
            ->selectRaw('content_type, COUNT(*) as count')
            ->groupBy('content_type')
            ->pluck('count', 'content_type')
            ->all();
    }

    private function loadGlobalSettingsData(array &$data): void
    {
        $data['autoResponseEnabled'] = (bool) AiSetting::get('ai_auto_response_enabled', false);
    }

    /**
     * Calculate storage info for a specific bot or all bots.
     */
    private function calculateStorageInfo(?AiBot $bot = null): array
    {
        $query = AiBusinessInfo::query();

        if ($bot !== null) {
            $query->where('ai_bot_id', $bot->id);
        }

        $totalDocuments = (clone $query)->count();
        $documentsWithFiles = (clone $query)->whereNotNull('file_path')->count();

        $totalSize = 0;
        $entriesWithFiles = (clone $query)->whereNotNull('file_path')->get(['file_path']);
        foreach ($entriesWithFiles as $entry) {
            if ($entry->file_path && Storage::disk('local')->exists($entry->file_path)) {
                $totalSize += Storage::disk('local')->size($entry->file_path);
            }
        }

        return [
            'total_documents' => $totalDocuments,
            'documents_with_files' => $documentsWithFiles,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / (1024 * 1024), 2),
        ];
    }
}
