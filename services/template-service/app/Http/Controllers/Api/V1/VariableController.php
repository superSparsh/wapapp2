<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateVariableRequest;
use App\Http\Requests\UpdateTemplateVariableRequest;
use App\Models\Variable;
use App\Services\TemplateVariableService;
use App\Support\BuiltinVariableCatalog;
use App\Support\TemplateVariablePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariableController extends Controller
{
    public function __construct(
        private readonly TemplateVariableService $variableService,
        private readonly TemplateVariablePresenter $presenter,
        private readonly BuiltinVariableCatalog $builtinCatalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $keyword = $request->string('q')->trim()->toString() ?: null;
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 10));

        $paginator = $this->variableService->paginate($keyword, $perPage, $page);
        $rows = $this->presenter->tableRows($paginator);

        return response()->json([
            'items' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $custom = $this->variableService->all()
            ->map(fn (Variable $variable): array => [
                'name' => $variable->name,
                'label' => $variable->name,
                'display_name' => ucwords(str_replace('_', ' ', $variable->name)),
                'description' => $variable->data_type->label().' variable',
                'syntax' => '$('.$variable->name.')',
                'type' => 'custom',
                'category' => 'custom',
                'data_type' => $variable->data_type->value,
            ])
            ->values()
            ->all();

        return response()->json([
            'custom' => $custom,
            'builtin' => $this->builtinCatalog->all(),
        ]);
    }

    public function store(StoreTemplateVariableRequest $request): JsonResponse
    {
        $variable = $this->variableService->create($request->validated());

        return response()->json([
            'variable' => $variable,
            'message' => 'Variable created successfully.',
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $variable = $this->variableService->findByUuid($uuid);

        if (! $variable instanceof Variable) {
            return response()->json(['error' => 'Variable not found.'], 404);
        }

        return response()->json([
            'variable' => $variable,
        ]);
    }

    public function update(UpdateTemplateVariableRequest $request, string $uuid): JsonResponse
    {
        $variable = $this->variableService->findByUuid($uuid);

        if (! $variable instanceof Variable) {
            return response()->json(['error' => 'Variable not found.'], 404);
        }

        $updated = $this->variableService->update($variable, $request->validated());

        return response()->json([
            'variable' => $updated,
            'message' => 'Variable updated successfully.',
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $variable = $this->variableService->findByUuid($uuid);

        if (! $variable instanceof Variable) {
            return response()->json(['error' => 'Variable not found.'], 404);
        }

        $this->variableService->delete($variable);

        return response()->json([
            'success' => true,
            'message' => 'Variable deleted successfully.',
        ]);
    }
}
