<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Http\Controllers;

use App\Domains\FormBuilder\Enums\FieldType;
use App\Domains\FormBuilder\Http\Requests\StoreFormRequest;
use App\Domains\FormBuilder\Http\Requests\UpdateFormRequest;
use App\Domains\FormBuilder\Services\FormBuilderService;
use App\Domains\FormBuilder\Support\FormCatalogPresenter;
use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use App\Http\Controllers\Controller;
use App\Models\MailList;
use App\Models\SignupForm;
use App\Models\Template;
use App\Support\ListingSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormBuilderController extends Controller
{
    public function __construct(
        private readonly FormBuilderService $formBuilderService,
        private readonly FormCatalogPresenter $presenter,
    ) {}

    /**
     * Display the form builder index with paginated, filtered forms.
     */
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $page = max(1, (int) $request->string('page')->toString());
        $perPage = (int) config('form-builder.per_page', 10);
        $parsed = ListingSort::fromRequest($request, ['created_at', 'name', 'status'], 'created_at', 'desc');

        $query = SignupForm::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"));

        ListingSort::apply($query, $parsed['sort'], $parsed['direction'], [
            'created_at' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ], 'created_at');

        $paginator = $query->paginate($perPage)->withQueryString();
        $rows = $this->presenter->tableRows($paginator->getCollection(), $page, $perPage);

        $pagination = [
            'total' => $paginator->total(),
            'per_page' => $perPage,
            'current' => $paginator->currentPage(),
            'pages' => $paginator->lastPage(),
        ];

        return view('form-builder.index', [
            'rows' => $rows,
            'pagination' => $pagination,
            'search' => $search,
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
            'paginator' => $paginator,
        ]);
    }

    /**
     * Show the form creation page with available field types and templates.
     */
    public function create(): View
    {
        $fieldTypes = FieldType::availableForBuilder();

        return view('form-builder.create', [
            'fieldTypes' => $fieldTypes,
            'templates' => $this->approvedTemplatesForSelect(),
            'mailLists' => $this->activeMailListsForSelect(),
            'defaultFields' => FieldType::defaultFields(),
        ]);
    }

    /**
     * Store a newly created form.
     */
    public function store(StoreFormRequest $request): RedirectResponse
    {
        $form = $this->formBuilderService->create($request->validated());

        return redirect()
            ->route('form-builder.edit', $form)
            ->with('status', 'Form created successfully.');
    }

    /**
     * Show the form editing page with existing form data.
     */
    public function edit(SignupForm $form): View
    {
        $form->load(['template', 'whatsappLine', 'mailList']);
        $fieldTypes = FieldType::availableForBuilder();

        return view('form-builder.edit', [
            'form' => $form,
            'fieldTypes' => $fieldTypes,
            'templates' => $this->approvedTemplatesForSelect(),
            'mailLists' => $this->activeMailListsForSelect(),
            'fields' => FormFieldNormalizer::normalizeList($form->fields ?? []),
        ]);
    }

    /**
     * Show form statistics (submissions + WhatsApp delivery).
     */
    public function statistics(SignupForm $form): View
    {
        $form->load(['mailList', 'template']);
        $stats = $form->statsArray();
        $total = max(1, (int) ($stats['total'] ?? 0));
        $pct = static fn (int $count): string => (string) round(($count / $total) * 100, 1);

        $recentSubmissions = $form->submissions()
            ->latest('id')
            ->limit(20)
            ->get(['id', 'phone', 'message_status', 'created_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at']);

        return view('form-builder.statistics', [
            'form' => $form,
            'stats' => $stats,
            'percent' => [
                'sent' => $pct((int) $stats['sent']),
                'delivered' => $pct((int) $stats['delivered']),
                'read' => $pct((int) $stats['read']),
                'failed' => $pct((int) $stats['failed']),
            ],
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    /**
     * Update an existing form.
     */
    public function update(UpdateFormRequest $request, SignupForm $form): RedirectResponse
    {
        $this->formBuilderService->update($form, $request->validated());

        return redirect()
            ->route('form-builder.edit', $form)
            ->with('status', 'Form updated successfully.');
    }

    /**
     * Soft-delete a form.
     */
    public function destroy(Request $request, SignupForm $form): RedirectResponse|JsonResponse
    {
        $this->formBuilderService->delete($form);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Form deleted.']);
        }

        return redirect()
            ->route('form-builder.index', $request->only(['q', 'page']))
            ->with('status', 'Form deleted successfully.');
    }

    /**
     * Toggle the form's active/inactive status.
     */
    public function toggleStatus(SignupForm $form): RedirectResponse|JsonResponse
    {
        $form = $this->formBuilderService->toggleStatus($form);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'status' => 'success',
                'is_active' => $form->isActive(),
                'status_label' => $form->status->label(),
            ]);
        }

        return redirect()->back()->with('status', 'Form status updated.');
    }

    /**
     * Upload a logo image for the form.
     */
    public function uploadLogo(Request $request, FormBuilderService $service): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:'.implode(',', config('form-builder.logo_mimes', ['png', 'jpg', 'jpeg'])), 'max:'.config('form-builder.logo_max_kb', 2048)],
        ]);

        $path = $service->storeLogo($request->file('logo'));

        return response()->json([
            'path' => $path,
            'url' => $service->logoPreviewUrl($path),
        ]);
    }

    /**
     * Stream a logo from the tenant public disk (builder preview).
     */
    public function showLogo(string $path, FormBuilderService $service): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $service->streamLogo($path);
    }

    /**
     * Latest approved templates first for create/edit selects.
     *
     * @return \Illuminate\Support\Collection<int|string, string>
     */
    private function approvedTemplatesForSelect()
    {
        return Template::query()
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('name', 'id');
    }

    /**
     * Latest active mail lists first for create/edit selects.
     *
     * @return \Illuminate\Support\Collection<int|string, string>
     */
    private function activeMailListsForSelect()
    {
        return MailList::query()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('name', 'id');
    }
}
