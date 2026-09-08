<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Http\Requests\StoreTemplateVariableRequest;
use App\Domains\Templates\Http\Requests\UpdateTemplateVariableRequest;
use App\Domains\Templates\Services\BuiltinVariableCatalog;
use App\Domains\Templates\Services\TemplateVariableQueryService;
use App\Domains\Templates\Services\TemplateVariableService;
use App\Domains\Templates\Support\TemplateVariablePresenter;
use App\Domains\Templates\Enums\VariableDataType;
use App\Http\Controllers\Controller;
use App\Models\Variable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateVariableController extends Controller
{
    public function index(
        Request $request,
        TemplateVariableQueryService $queryService,
        TemplateVariablePresenter $presenter,
    ): View {
        $search = $request->string('q')->trim()->toString();
        $paginator = $queryService->paginate($search !== '' ? $search : null);

        return view('templates.variables', [
            'variables' => $presenter->tableRows($paginator),
            'paginator' => $paginator,
            'search' => $search,
        ]);
    }

    public function create(TemplateVariablePresenter $presenter): View
    {
        return view('templates.variables.create', [
            'variable' => null,
            'form' => $presenter->formState(),
            'dataTypes' => VariableDataType::selectable(),
        ]);
    }

    public function store(
        StoreTemplateVariableRequest $request,
        TemplateVariableService $service,
    ): RedirectResponse {
        $service->create(
            $request->validated(),
            $request->file('file'),
        );

        return redirect()
            ->route('templates.variables')
            ->with('status', 'Variable created successfully.');
    }

    public function edit(Variable $variable, TemplateVariablePresenter $presenter): View
    {
        return view('templates.variables.create', [
            'variable' => $variable,
            'form' => $presenter->formState($variable),
            'dataTypes' => VariableDataType::selectable(),
        ]);
    }

    public function update(
        Variable $variable,
        UpdateTemplateVariableRequest $request,
        TemplateVariableService $service,
    ): RedirectResponse {
        $service->update(
            $variable,
            $request->validated(),
            $request->file('file'),
        );

        return redirect()
            ->route('templates.variables')
            ->with('status', 'Variable updated successfully.');
    }

    public function destroy(
        Variable $variable,
        TemplateVariableService $service,
    ): RedirectResponse {
        $service->delete($variable);

        return redirect()
            ->route('templates.variables')
            ->with('status', 'Variable deleted successfully.');
    }

    public function chatbot(BuiltinVariableCatalog $catalog): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'message' => 'Chatbot variables retrieved successfully',
            'variables' => $catalog->all(),
            'total_count' => count($catalog->all()),
        ]);
    }
}
