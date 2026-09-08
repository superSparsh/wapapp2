<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Controllers;

use App\Domains\Chatbot\Http\Requests\StoreChatbotFlowRequest;
use App\Domains\Chatbot\Http\Requests\UpdateChatbotFlowRequest;
use App\Domains\Chatbot\Services\ChatbotFlowPresenter;
use App\Domains\Chatbot\Services\ChatbotFlowQueryService;
use App\Domains\Chatbot\Services\ChatbotFlowService;
use App\Domains\Chatbot\Services\ChatbotFlowStatService;
use App\Http\Controllers\Controller;
use App\Models\ChatbotFlow;
use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotFlowController extends Controller
{
    public function __construct(
        private readonly ChatbotFlowQueryService $queryService,
        private readonly ChatbotFlowService $flowService,
        private readonly ChatbotFlowPresenter $presenter,
    ) {}

    public function index(Request $request): View
    {
        $paginator = $this->queryService->paginate(
            perPage: (int) config('chatbot.per_page', 10),
            search: $request->query('search'),
            status: $request->query('status'),
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        return view('automation.chatbot', [
            'chatbots' => $this->presenter->tableRows($paginator),
            'paginator' => $paginator,
            'statusOptions' => $this->presenter->statusOptions(),
            'currentSort' => $request->query('sort', 'created_at'),
            'currentDirection' => $request->query('direction', 'desc'),
            'whatsappLines' => WhatsappLine::query()
                ->select(['id', 'uuid', 'phone', 'display_name', 'status'])
                ->orderBy('display_name')
                ->get(),
            'showWhatsappLinePicker' => WhatsappLine::query()->count() > 1,
        ]);
    }

    public function create(): View
    {
        return view('automation.chatbot-create', [
            'whatsappLines' => WhatsappLine::query()
                ->select(['id', 'uuid', 'phone', 'display_name', 'status'])
                ->orderBy('display_name')
                ->get(),
            'showWhatsappLinePicker' => WhatsappLine::query()->count() > 1,
        ]);
    }

    public function store(StoreChatbotFlowRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['whatsapp_line_id'])) {
            $line = PublicId::find(WhatsappLine::class, (string) $data['whatsapp_line_id']);
            $data['whatsapp_line_id'] = $line?->id;
        }

        $flow = $this->flowService->create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'flow_id' => $flow->id,
                'name' => $flow->name,
                'edit_url' => route('chatbot.edit', $flow),
            ], 201);
        }

        return redirect()
            ->route('chatbot.edit', $flow)
            ->with('status', 'Chatbot flow created successfully.');
    }

    public function show(ChatbotFlow $chatbotFlow): View
    {
        return view('automation.chatbot-show', [
            'flow' => $chatbotFlow,
        ]);
    }

    public function edit(ChatbotFlow $chatbotFlow): View
    {
        return view('automation.chatbot-flow', [
            'flow' => $chatbotFlow,
        ]);
    }

    public function update(UpdateChatbotFlowRequest $request, ChatbotFlow $chatbotFlow): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        if (array_key_exists('whatsapp_line_id', $data)) {
            $line = PublicId::find(WhatsappLine::class, (string) ($data['whatsapp_line_id'] ?? ''));
            $data['whatsapp_line_id'] = $line?->id;
        }

        $this->flowService->update($chatbotFlow, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'name' => $chatbotFlow->fresh()->name,
            ]);
        }

        return redirect()
            ->route('chatbot.index')
            ->with('status', 'Chatbot flow updated successfully.');
    }

    public function destroy(ChatbotFlow $chatbotFlow): RedirectResponse
    {
        $this->flowService->delete($chatbotFlow);

        return redirect()
            ->route('chatbot.index')
            ->with('status', 'Chatbot flow deleted successfully.');
    }

    public function toggle(ChatbotFlow $chatbotFlow): RedirectResponse|JsonResponse
    {
        try {
            $this->flowService->toggle($chatbotFlow);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => collect($e->errors())->flatten()->first(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors($e->errors());
        }

        $fresh = $chatbotFlow->fresh();
        $active = $fresh->isActive();
        $status = $active ? 'activated' : 'deactivated';

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'active' => $active,
                'status' => $status,
            ]);
        }

        return redirect()
            ->route('chatbot.index')
            ->with('status', "Chatbot flow {$status} successfully.");
    }

    public function publish(ChatbotFlow $chatbotFlow): RedirectResponse
    {
        try {
            $this->flowService->publish($chatbotFlow);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('chatbot.index')
            ->with('status', 'Chatbot flow published successfully.');
    }

    public function duplicate(ChatbotFlow $chatbotFlow): RedirectResponse
    {
        $clone = $this->flowService->duplicate($chatbotFlow);

        return redirect()
            ->route('chatbot.edit', $clone)
            ->with('status', 'Chatbot flow duplicated successfully.');
    }

    public function stats(ChatbotFlow $chatbotFlow, ChatbotFlowStatService $statService): View
    {
        return view('automation.chatbot-stats', [
            'flow' => $chatbotFlow,
            'stats' => $statService->aggregate($chatbotFlow->id),
        ]);
    }
}
