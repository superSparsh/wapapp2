<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\PlatformTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformTemplateController extends Controller
{
    /** @var list<string> */
    private const TYPES = ['whatsapp', 'email', 'sms'];

    public function index(Request $request): View
    {
        $category = trim((string) $request->query('category', ''));
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['name', 'category', 'id'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $query = PlatformTemplate::query()
            ->when($category !== '', fn ($builder) => $builder->where('category', $category));

        AdminListQuery::applySearch($query, $parsed['q'], ['name', 'category', 'type']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'name' => 'name',
                'category' => 'category',
                'id' => 'id',
            ],
            'name',
        );

        return view('admin.platform-templates.index', [
            'rows' => $query->paginate(24)->withQueryString(),
            'categories' => PlatformTemplate::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'category' => $category,
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'category', 'label' => 'Category', 'direction' => 'asc'],
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.platform-templates.form', ['types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        PlatformTemplate::query()->create($this->validated($request));

        return redirect()->route('admin.platform-templates.index')->with('status', 'Template created.');
    }

    public function edit(PlatformTemplate $template): View
    {
        return view('admin.platform-templates.form', [
            'row' => $template,
            'types' => self::TYPES,
        ]);
    }

    public function update(Request $request, PlatformTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return redirect()->route('admin.platform-templates.index')->with('status', 'Template updated.');
    }

    public function destroy(PlatformTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', 'Template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PlatformTemplate $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'body' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
