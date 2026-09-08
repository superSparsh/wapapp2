<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Models\Template;
use App\Repositories\Interfaces\TemplateRepositoryInterface;
use App\Services\TemplatePreviewService;
use App\Services\TemplateService;
use App\Support\TemplateCatalogPresenter;
use App\Support\TemplateCategoryCatalog;
use App\Support\TemplateLanguageCatalog;
use App\Support\VariableActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateService $templateService,
        private readonly TemplatePreviewService $previewService,
        private readonly TemplateCatalogPresenter $presenter,
        private readonly VariableActorContext $actorContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $keyword = $request->string('q')->trim()->toString() ?: null;
        $category = $request->string('category')->trim()->toString() ?: null;
        $type = $request->string('type')->trim()->toString() ?: null;
        $approvedOnly = $request->boolean('approved_only');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 10));

        $paginator = $this->templateRepository->paginate(
            keyword: $keyword,
            category: $category,
            type: $type,
            approvedOnly: $approvedOnly,
            whatsappLineId: $this->actorContext->whatsappLineId(),
            perPage: $perPage,
            page: $page,
        );

        $templates = collect($paginator->items());
        $rows = $this->presenter->tableRows($templates, 1, $perPage);

        return response()->json([
            'items' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'categories' => TemplateCategoryCatalog::builderValues(),
            'types' => ['Regular', 'Draft'],
            'languages' => TemplateLanguageCatalog::options(),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $options = $this->templateRepository->approvedOptions($this->actorContext->whatsappLineId());

        return response()->json([
            'items' => $options,
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $template = $this->templateRepository->findByUuid($uuid);

        if (! $template instanceof Template) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        return response()->json([
            'template' => $template,
            'preview' => $this->previewService->forTemplate($template),
        ]);
    }

    public function preview(string $code): JsonResponse
    {
        return response()->json($this->previewService->forCode($code));
    }

    public function draft(): JsonResponse
    {
        $template = $this->templateService->createDraft();

        return response()->json([
            'template' => $template,
            'message' => 'Draft template created successfully.',
        ], 201);
    }

    public function fromSetup(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->createFromSetup($request->validated());

        return response()->json([
            'template' => $template,
            'message' => 'Template created from setup.',
        ], 201);
    }

    public function saveStep(Request $request, string $uuid, string $step): JsonResponse
    {
        $template = $this->templateRepository->findByUuid($uuid);

        if (! $template instanceof Template) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        $stepData = $request->input('step_data', $request->except(['_service_token']));
        $updated = $this->templateService->saveStep($template, $step, (array) $stepData);

        return response()->json([
            'template' => $updated,
            'message' => "Step {$step} saved successfully.",
        ]);
    }

    public function submit(string $uuid): JsonResponse
    {
        $template = $this->templateRepository->findByUuid($uuid);

        if (! $template instanceof Template) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        $submitted = $this->templateService->submit($template);

        return response()->json([
            'template' => $submitted,
            'message' => 'Template submitted successfully.',
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $template = $this->templateRepository->findByUuid($uuid);

        if (! $template instanceof Template) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        $this->templateService->delete($template);

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'uuids' => ['required', 'array'],
            'uuids.*' => ['required', 'string'],
        ]);

        $deletedCount = $this->templateService->bulkDelete((array) $request->input('uuids'));

        return response()->json([
            'success' => true,
            'deleted' => $deletedCount,
            'message' => "Successfully deleted {$deletedCount} templates.",
        ]);
    }
}
