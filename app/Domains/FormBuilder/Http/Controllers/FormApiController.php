<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Http\Controllers;

use App\Domains\FormBuilder\Services\FormBuilderService;
use App\Domains\FormBuilder\Support\FormCatalogPresenter;
use App\Http\Controllers\Controller;
use App\Models\SignupForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormApiController extends Controller
{
    public function __construct(
        private readonly FormBuilderService $formBuilderService,
        private readonly FormCatalogPresenter $presenter,
    ) {}

    /**
     * Return paginated forms as JSON (for AJAX table loading).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('q')->trim()->toString();
        $page = max(1, (int) $request->string('page')->toString());
        $perPage = (int) config('form-builder.per_page', 10);

        $query = SignupForm::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at');

        $total = $query->count();
        $forms = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return response()->json([
            'rows' => $this->presenter->tableRows($forms, $page, $perPage),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current' => $page,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Bulk-delete forms by UUIDs.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuids' => ['required', 'array', 'min:1'],
            'uuids.*' => ['required', 'string'],
        ]);

        $deleted = $this->formBuilderService->bulkDelete($validated['uuids']);

        return response()->json([
            'status' => 'success',
            'message' => "{$deleted} form(s) deleted.",
            'deleted' => $deleted,
            'total' => count($validated['uuids']),
        ]);
    }

    /**
     * Get statistics for a specific form.
     */
    public function stats(SignupForm $form): JsonResponse
    {
        return response()->json([
            'form' => [
                'uuid' => $form->uuid,
                'name' => $form->name,
                'stats' => $form->statsArray(),
            ],
        ]);
    }
}
