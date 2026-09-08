<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Controllers;

use App\Domains\WhatsappFlow\Http\Requests\StoreWhatsappFlowRequest;
use App\Domains\WhatsappFlow\Http\Requests\UpdateWhatsappFlowRequest;
use App\Domains\WhatsappFlow\Services\WhatsappFlowCamsService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowPresenter;
use App\Domains\WhatsappFlow\Services\WhatsappFlowQueryService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowService;
use App\Http\Controllers\Controller;
use App\Models\WhatsappFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappFlowController extends Controller
{
    public function __construct(
        private readonly WhatsappFlowQueryService $queryService,
        private readonly WhatsappFlowService $flowService,
        private readonly WhatsappFlowCamsService $camsService,
        private readonly WhatsappFlowPresenter $presenter,
    ) {}

    public function index(Request $request): View
    {
        $paginator = $this->queryService->paginate(
            perPage: (int) config('whatsapp-flows.per_page', 10),
            search: $request->query('search'),
            status: $request->query('status'),
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        return view('whatsapp-flows.index', [
            'flows' => $this->presenter->tableRows($paginator),
            'paginator' => $paginator,
            'statusOptions' => $this->presenter->statusOptions(),
            'currentSort' => $request->query('sort', 'created_at'),
            'currentDirection' => $request->query('direction', 'desc'),
        ]);
    }

    public function create(): View
    {
        return view('whatsapp-flows.create');
    }

    public function store(StoreWhatsappFlowRequest $request): RedirectResponse|JsonResponse
    {
        $flow = $this->flowService->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'flow_id' => $flow->id,
                'name' => $flow->name,
            ], 201);
        }

        return redirect()
            ->route('whatsapp-flows.edit', $flow)
            ->with('status', 'WhatsApp Flow created successfully.');
    }

    public function show(WhatsappFlow $whatsappFlow): View
    {
        $submissions = $whatsappFlow->submissions()
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('whatsapp-flows.show', [
            'flow' => $whatsappFlow,
            'submissions' => $submissions,
        ]);
    }

    public function edit(WhatsappFlow $whatsappFlow): View
    {
        return view('whatsapp-flows.edit', [
            'flow' => $whatsappFlow,
        ]);
    }

    public function update(UpdateWhatsappFlowRequest $request, WhatsappFlow $whatsappFlow): RedirectResponse|JsonResponse
    {
        $this->flowService->update($whatsappFlow, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'name' => $whatsappFlow->fresh()->name,
            ]);
        }

        return redirect()
            ->route('whatsapp-flows.index')
            ->with('status', 'WhatsApp Flow updated successfully.');
    }

    public function destroy(WhatsappFlow $whatsappFlow): RedirectResponse
    {
        $this->flowService->delete($whatsappFlow);

        return redirect()
            ->route('whatsapp-flows.index')
            ->with('status', 'WhatsApp Flow deleted.');
    }

    public function publish(WhatsappFlow $whatsappFlow): RedirectResponse|JsonResponse
    {
        $flow = $this->flowService->publish($whatsappFlow);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => $flow->status->value,
                'endpoint' => $flow->data_exchange_endpoint,
            ]);
        }

        return back()->with('status', 'Flow published successfully.');
    }

    public function preview(WhatsappFlow $whatsappFlow): JsonResponse
    {
        $url = $this->camsService->previewUrl($whatsappFlow);

        if ($url === null) {
            return response()->json([
                'success' => false,
                'message' => 'Preview is unavailable. Publish the flow or configure CAMS.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'preview_url' => $url,
        ]);
    }

    public function checkName(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'except_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'available' => $this->flowService->isNameAvailable(
                $validated['name'],
                isset($validated['except_id']) ? (int) $validated['except_id'] : null,
            ),
        ]);
    }

    public function archive(WhatsappFlow $whatsappFlow): RedirectResponse
    {
        $this->flowService->archive($whatsappFlow);

        return back()->with('status', 'Flow archived.');
    }

    public function duplicate(WhatsappFlow $whatsappFlow): RedirectResponse
    {
        $clone = $this->flowService->duplicate($whatsappFlow);

        return redirect()
            ->route('whatsapp-flows.edit', $clone)
            ->with('status', 'Flow duplicated successfully.');
    }

    public function stats(WhatsappFlow $whatsappFlow): View
    {
        $total = $whatsappFlow->submissions()->count();
        $processed = $whatsappFlow->submissions()->where('status', 'processed')->count();
        $failed = $whatsappFlow->submissions()->where('status', 'failed')->count();
        $recent = $whatsappFlow->submissions()->orderByDesc('id')->limit(50)->get();

        // Extract field label mapping from flow_json screens
        $fieldMap = [];
        if (is_array($whatsappFlow->flow_json) && isset($whatsappFlow->flow_json['screens'])) {
            foreach ($whatsappFlow->flow_json['screens'] as $screen) {
                foreach ($screen['fields'] ?? [] as $field) {
                    if (isset($field['name'])) {
                        $fieldMap[$field['name']] = $field['label'] ?? $field['name'];
                    }
                }
            }
        }

        // Collect all distinct submission keys (excluding internal tokens)
        $submissionKeys = [];
        foreach ($recent as $sub) {
            if (is_array($sub->form_data)) {
                foreach (array_keys($sub->form_data) as $k) {
                    if ($k !== 'flow_token' && ! in_array($k, $submissionKeys, true)) {
                        $submissionKeys[] = $k;
                    }
                }
            }
        }

        return view('whatsapp-flows.stats', [
            'flow' => $whatsappFlow,
            'stats' => [
                'total' => $total,
                'processed' => $processed,
                'failed' => $failed,
                'rate' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            ],
            'recentSubmissions' => $recent,
            'submissionKeys' => $submissionKeys,
            'fieldMap' => $fieldMap,
        ]);
    }
}
