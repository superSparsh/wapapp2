<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Http\Requests\StoreAiBotRequest;
use App\Domains\AiBot\Http\Requests\UpdateAiBotRequest;
use App\Domains\AiBot\Services\AiBotQueryService;
use App\Domains\AiBot\Services\AiBotService;
use App\Domains\AiBot\Services\AiTokenUsageService;
use App\Domains\Integration\Services\PhoneLineService;
use App\Http\Controllers\Controller;
use App\Models\AiBot;
use App\Models\WhatsappLine;
use App\Support\BulkDestroy;
use App\Support\ListingSort;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiBotController extends Controller
{
    public function __construct(
        private readonly AiBotQueryService $queryService,
        private readonly AiBotService $botService,
    ) {}

    public function index(Request $request): View
    {
        $parsed = ListingSort::fromRequest($request, ['created_at', 'name', 'status'], 'created_at', 'desc');
        $lineFilter = PhoneLineService::lockedLineId();
        $bots = $this->queryService->paginate(
            perPage: 10,
            search: $request->query('search'),
            sort: $parsed['sort'],
            direction: $parsed['direction'],
            whatsappLineId: $lineFilter,
        );

        return view('ai-bots.index', [
            'bots' => $bots,
            'search' => $request->query('search', ''),
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
            'whatsappLines' => $this->whatsappLinesForUi(),
        ]);
    }

    public function create(): View
    {
        return view('ai-bots.create', [
            'whatsappLines' => $this->whatsappLinesForUi(),
            'lineContextLocked' => PhoneLineService::isLocked(),
            'selectedWhatsappLineUuid' => PhoneLineService::lockedLineId() !== null
                ? WhatsappLine::query()->find(PhoneLineService::lockedLineId())?->uuid
                : null,
        ]);
    }

    public function store(StoreAiBotRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->resolveWhatsappLineId($data);
        $this->botService->create($data);

        return redirect()
            ->route('openai-key.index', ['tab' => 'bot-manager'])
            ->with('status', 'AI bot created successfully.');
    }

    public function show(AiBot $aiBot): View
    {
        $aiBot->load(['businessInfoEntries', 'tokenUsageLogs' => fn ($q) => $q->latest()->limit(20)]);

        return view('ai-bots.show', [
            'bot' => $aiBot,
        ]);
    }

    public function edit(AiBot $aiBot): View
    {
        $this->botService->assertCanManage($aiBot);

        return view('ai-bots.edit', [
            'bot' => $aiBot,
            'whatsappLines' => $this->whatsappLinesForUi(),
            'lineContextLocked' => PhoneLineService::isLocked(),
        ]);
    }

    public function update(UpdateAiBotRequest $request, AiBot $aiBot): RedirectResponse
    {
        $data = $request->validated();
        $this->resolveWhatsappLineId($data);
        $this->botService->update($aiBot, $data);

        return redirect()
            ->route('openai-key.index', ['tab' => 'bot-manager'])
            ->with('status', 'AI bot updated successfully.');
    }

    public function destroy(AiBot $aiBot): RedirectResponse
    {
        $this->botService->delete($aiBot);

        return redirect()
            ->route('openai-key.index', ['tab' => 'bot-manager'])
            ->with('status', 'AI bot deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveWhatsappLineId(array &$data): void
    {
        $lockedLineId = PhoneLineService::lockedLineId();
        if ($lockedLineId !== null) {
            $data['whatsapp_line_id'] = $lockedLineId;

            return;
        }

        if (! array_key_exists('whatsapp_line_id', $data)) {
            return;
        }

        $line = PublicId::find(WhatsappLine::class, (string) $data['whatsapp_line_id']);
        $data['whatsapp_line_id'] = $line?->id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, WhatsappLine>
     */
    private function whatsappLinesForUi()
    {
        $lockedLineId = PhoneLineService::lockedLineId();
        $query = WhatsappLine::query()->orderByDesc('is_default')->orderBy('id');

        if ($lockedLineId !== null) {
            $query->where('id', $lockedLineId);
        }

        return $query->get();
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        return BulkDestroy::byUuids(
            $request,
            AiBot::class,
            'bot(s)',
            fn (AiBot $bot) => $this->botService->delete($bot),
        );
    }

    public function toggleDefault(AiBot $aiBot): RedirectResponse
    {
        $this->botService->toggleDefault($aiBot);

        $status = $aiBot->fresh()->is_default ? 'set as default' : 'unset as default';

        return redirect()
            ->route('openai-key.index', ['tab' => 'bot-manager'])
            ->with('status', "AI bot {$status} successfully.");
    }

    public function usage(AiBot $aiBot, AiTokenUsageService $usageService): View
    {
        return view('ai-bots.usage', [
            'bot' => $aiBot,
            'stats' => $usageService->botStats($aiBot->id),
        ]);
    }
}
