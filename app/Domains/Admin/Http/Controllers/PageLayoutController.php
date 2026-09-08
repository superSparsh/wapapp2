<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PageLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageLayoutController extends Controller
{
    public function index(): View
    {
        return view('admin.page-layouts.index', [
            'rows' => PageLayout::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.page-layouts.form');
    }

    public function store(Request $request): RedirectResponse
    {
        PageLayout::query()->create($this->validated($request));

        return redirect()->route('admin.page-layouts.index')->with('status', 'Page layout created.');
    }

    public function edit(PageLayout $pageLayout): View
    {
        return view('admin.page-layouts.form', ['row' => $pageLayout]);
    }

    public function update(Request $request, PageLayout $pageLayout): RedirectResponse
    {
        $pageLayout->update($this->validated($request, $pageLayout));

        return redirect()->route('admin.page-layouts.index')->with('status', 'Page layout updated.');
    }

    public function destroy(PageLayout $pageLayout): RedirectResponse
    {
        $pageLayout->delete();

        return back()->with('status', 'Page layout deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PageLayout $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $query = PageLayout::query()->where('slug', Str::slug((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This slug is already taken.');
                    }
                },
            ],
            'alias' => ['nullable', 'string', 'max:64'],
            'html' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = Str::slug(($data['slug'] ?? '') !== '' ? (string) $data['slug'] : $data['name']);
        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
