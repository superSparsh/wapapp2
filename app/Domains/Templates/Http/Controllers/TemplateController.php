<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Jobs\DeleteTemplateJob;
use App\Domains\Templates\Services\InteractiveMessageService;
use App\Domains\Templates\Services\TemplateCatalogService;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateServiceAdapter;
use App\Domains\Templates\Support\InteractiveMessagePresenter;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateServiceAdapter $adapter,
    ) {}

    public function index(
        Request $request,
        TemplateCatalogService $catalogService,
        TemplatePreviewService $previewService,
        InteractiveMessageService $interactiveMessageService,
        InteractiveMessagePresenter $interactiveMessagePresenter,
    ): View|RedirectResponse {
        if ($request->boolean('refresh')) {
            $catalogService->refresh();

            return redirect()->route('templates.index', $request->except('refresh'));
        }

        $activeTab = $request->string('tab')->toString() ?: 'approved';
        $search = $request->string('q')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $freeType = $request->string('free_type')->trim()->toString();
        $page = max(1, (int) $request->string('page')->toString());
        $perPage = 10;
        $code = $request->filled('code') ? $request->string('code')->toString() : null;
        $draftUuid = $request->filled('draft') ? $request->string('draft')->toString() : null;
        $showPreview = $request->boolean('preview');

        $viewData = $this->adapter->indexViewData(
            search: $search !== '' ? $search : null,
            category: $category !== '' ? $category : null,
            type: $type !== '' ? $type : null,
            page: $page,
            perPage: $perPage,
            code: $code,
            draftUuid: $draftUuid,
            showPreviewParam: $showPreview,
            activeTab: $activeTab,
            freeType: $freeType,
            interactiveMessageService: $interactiveMessageService,
            interactiveMessagePresenter: $interactiveMessagePresenter,
        );

        return view('templates.index', $viewData);
    }

    /**
     * Delete a single template (soft-delete).
     * If the template has a WhatsApp template code, it is marked for async deletion.
     */
    public function destroy(Request $request, Template $template): RedirectResponse|JsonResponse
    {
        $needsWhatsAppDelete = filled($template->whatsappCode());

        $this->adapter->delete($template);

        if ($needsWhatsAppDelete) {
            DeleteTemplateJob::dispatch($template->id);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Template deleted.']);
        }

        return redirect()
            ->route('templates.index', $request->only(['tab', 'q', 'category', 'type', 'page']))
            ->with('status', 'Template deleted successfully.');
    }

    /**
     * Bulk-delete templates by UUID.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuids' => ['required', 'array', 'min:1'],
            'uuids.*' => ['required', 'string'],
        ]);

        $deleted = $this->adapter->bulkDelete((array) $validated['uuids']);

        return response()->json([
            'status' => 'success',
            'message' => "{$deleted} template(s) deleted.",
            'deleted' => $deleted,
            'total' => count($validated['uuids']),
        ]);
    }
}
