<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Http\Requests\StoreBusinessInfoRequest;
use App\Domains\AiBot\Services\AiBusinessInfoService;
use App\Domains\AiBot\Services\AiEmbeddingService;
use App\Http\Controllers\Controller;
use App\Models\AiBot;
use App\Models\AiBusinessInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiBusinessInfoController extends Controller
{
    public function __construct(
        private readonly AiBusinessInfoService $businessInfoService,
        private readonly AiEmbeddingService $embeddingService,
    ) {}

    public function index(AiBot $aiBot): View
    {
        $entries = AiBusinessInfo::query()
            ->where('ai_bot_id', $aiBot->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('ai-bots.business-info', [
            'bot' => $aiBot,
            'entries' => $entries,
        ]);
    }

    public function store(StoreBusinessInfoRequest $request, AiBot $aiBot): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file')) {
            $this->businessInfoService->upload(
                $aiBot,
                $request->file('file'),
                $validated['title'],
            );
        } else {
            $this->businessInfoService->create($aiBot, $validated);
        }

        return redirect()
            ->route('ai-bots.business-info.index', $aiBot)
            ->with('status', 'Business info added successfully.');
    }

    public function update(Request $request, AiBot $aiBot, AiBusinessInfo $business_info): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $this->businessInfoService->update($business_info, $validated);

        return redirect()
            ->route('ai-bots.business-info.index', $aiBot)
            ->with('status', 'Business info updated successfully.');
    }

    public function destroy(AiBot $aiBot, AiBusinessInfo $business_info): RedirectResponse
    {
        $this->businessInfoService->delete($business_info);

        return redirect()
            ->route('ai-bots.business-info.index', $aiBot)
            ->with('status', 'Business info deleted successfully.');
    }

    public function upload(StoreBusinessInfoRequest $request, AiBot $aiBot): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file')) {
            $entry = $this->businessInfoService->upload(
                $aiBot,
                $request->file('file'),
                $validated['title'],
            );

            // Trigger embedding processing
            $this->embeddingService->processEmbedding($entry, $aiBot);
        }

        return redirect()
            ->route('ai-bots.business-info.index', $aiBot)
            ->with('status', 'File uploaded successfully.');
    }
}
