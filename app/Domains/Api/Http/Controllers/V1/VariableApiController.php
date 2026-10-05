<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Enums\VariableType;
use App\Domains\Templates\Services\TemplateVariableQueryService;
use App\Domains\Templates\Services\TemplateVariableService;
use App\Http\Controllers\Controller;
use App\Models\Variable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VariableApiController extends Controller
{
    public function index(Request $request, TemplateVariableQueryService $query): JsonResponse
    {
        $paginator = $query->paginate(
            keyword: $request->query('search'),
            perPage: (int) $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Variable $v): array => $this->serialize($v))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, TemplateVariableService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(['Static', 'Dynamic', 'static', 'dynamic'])],
            'variable_type' => ['required', 'string', Rule::in(['String', 'Number', 'URL', 'Url', 'string', 'number', 'url'])],
            'value' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = strtolower((string) ($validated['type'] ?? 'dynamic')) === 'static'
            ? VariableType::Static
            : VariableType::Dynamic;

        $dataType = match (strtolower((string) $validated['variable_type'])) {
            'number' => VariableDataType::Number,
            'url' => VariableDataType::Url,
            default => VariableDataType::String,
        };

        $variable = $service->create([
            'name' => $validated['name'],
            'type' => $type,
            'data_type' => $dataType,
            'value' => $validated['value'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($variable),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Variable $variable): array
    {
        return [
            'uid' => $variable->uuid,
            'name' => $variable->name,
            'type' => $variable->type instanceof VariableType
                ? $variable->type->label()
                : (string) $variable->type,
            'variable_type' => $variable->data_type instanceof VariableDataType
                ? $variable->data_type->label()
                : (string) $variable->data_type,
            'value' => $variable->value,
            'created_at' => optional($variable->created_at)?->toIso8601String(),
        ];
    }
}
