<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Http\Requests\StoreAiBotRequest;
use App\Domains\AiBot\Http\Requests\UpdateAiBotRequest;
use App\Domains\AiBot\Services\AiBotQueryService;
use App\Domains\AiBot\Services\AiBotService;
use App\Domains\AiBot\Services\AiTokenUsageService;
use App\Http\Controllers\Controller;
use App\Models\AiBot;
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
        $bots = $this->queryService->paginate(
            perPage: 10,
            search: $request->query('search'),
        );

        return view('ai-bots.index', [
            'bots' => $bots,
        ]);
    }

    public function create(): View
    {
        return view('ai-bots.create');
    }

    public function store(StoreAiBotRequest $request): RedirectResponse
    {
        $bot = $this->botService->create($request->validated());

        return redirect()
            ->route('ai-bots.show', $bot)
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
        return view('ai-bots.edit', [
            'bot' => $aiBot,
        ]);
    }

    public function update(UpdateAiBotRequest $request, AiBot $aiBot): RedirectResponse
    {
        $this->botService->update($aiBot, $request->validated());

        return redirect()
            ->route('ai-bots.index')
            ->with('status', 'AI bot updated successfully.');
    }

    public function destroy(AiBot $aiBot): RedirectResponse
    {
        $this->botService->delete($aiBot);

        return redirect()
            ->route('ai-bots.index')
            ->with('status', 'AI bot deleted successfully.');
    }

    public function toggleDefault(AiBot $aiBot): RedirectResponse
    {
        $this->botService->toggleDefault($aiBot);

        $status = $aiBot->fresh()->is_default ? 'set as default' : 'unset as default';

        return redirect()
            ->route('ai-bots.index')
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
