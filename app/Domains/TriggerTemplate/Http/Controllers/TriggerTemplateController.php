<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Http\Controllers;

use App\Domains\TriggerTemplate\Http\Requests\StoreTriggerVariableRequest;
use App\Domains\TriggerTemplate\Services\TriggerTemplateOptionService;
use App\Domains\TriggerTemplate\Services\TriggerVariableQueryService;
use App\Domains\TriggerTemplate\Services\TriggerVariableService;
use App\Domains\TriggerTemplate\Support\TriggerPresenter;
use App\Http\Controllers\Controller;
use App\Models\TriggerVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TriggerTemplateController extends Controller
{
    public function index(
        Request $request,
        TriggerVariableQueryService $queryService,
        TriggerTemplateOptionService $optionService,
        TriggerPresenter $presenter,
    ): View {
        $paginator = $queryService->paginate((int) config('trigger-template.per_page', 10));
        $templates = $optionService->templates();
        $mailLists = $optionService->mailLists();

        return view('trigger-template.index', [
            'triggers' => $presenter->tableRows($paginator),
            'paginator' => $paginator,
            'templateOptions' => $presenter->templateOptions($templates),
            'mailListOptions' => $presenter->mailListOptions($mailLists),
            'openModal' => $request->query('modal') === 'add-trigger',
        ]);
    }

    public function store(
        StoreTriggerVariableRequest $request,
        TriggerVariableService $variableService,
        TriggerTemplateOptionService $optionService,
    ): RedirectResponse {
        $validated = $request->validated();
        $listId = isset($validated['list_id']) ? (int) $validated['list_id'] : null;
        $listName = null;

        if ($listId !== null) {
            $listName = $optionService->mailLists()
                ->firstWhere('id', $listId)['name'] ?? null;
        }

        $variableService->create([
            'variable_name' => $validated['variable_name'],
            'template_code' => $validated['template_code'],
            'template_name' => $validated['template_name'],
            'list_id' => $listName !== null ? $listId : null,
            'list_name' => $listName,
        ]);

        return redirect()
            ->route('trigger-template.index')
            ->with('status', 'Trigger created successfully.');
    }

    public function destroy(
        TriggerVariable $triggerVariable,
        TriggerVariableService $variableService,
    ): RedirectResponse {
        $variableService->delete($triggerVariable);

        return redirect()
            ->route('trigger-template.index')
            ->with('status', 'Trigger deleted successfully.');
    }
}
