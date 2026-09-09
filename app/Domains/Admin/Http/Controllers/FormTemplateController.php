<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\FormTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FormTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['name', 'id'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $query = FormTemplate::query();
        AdminListQuery::applySearch($query, $parsed['q'], ['name', 'slug']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            ['name' => 'name', 'id' => 'id'],
            'name',
        );

        return view('admin.form-templates.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.form-templates.form');
    }

    public function store(Request $request): RedirectResponse
    {
        FormTemplate::query()->create($this->validated($request));

        return redirect()->route('admin.form-templates.index')->with('status', 'Form template created.');
    }

    public function edit(FormTemplate $formTemplate): View
    {
        return view('admin.form-templates.form', ['row' => $formTemplate]);
    }

    public function update(Request $request, FormTemplate $formTemplate): RedirectResponse
    {
        $formTemplate->update($this->validated($request, $formTemplate));

        return redirect()->route('admin.form-templates.index')->with('status', 'Form template updated.');
    }

    public function destroy(FormTemplate $formTemplate): RedirectResponse
    {
        $formTemplate->delete();

        return back()->with('status', 'Form template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?FormTemplate $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $query = FormTemplate::query()->where('slug', Str::slug((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This slug is already taken.');
                    }
                },
            ],
            'html' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = Str::slug(($data['slug'] ?? '') !== '' ? (string) $data['slug'] : $data['name']);
        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
