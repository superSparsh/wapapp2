<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminFaqController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['sort_order', 'heading', 'id'],
            defaultSort: 'sort_order',
            defaultDirection: 'asc',
        );

        $query = Faq::query();
        AdminListQuery::applySearch($query, $parsed['q'], ['heading', 'description', 'slug']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'sort_order' => 'sort_order',
                'heading' => 'heading',
                'id' => 'id',
            ],
            'sort_order',
        );

        return view('admin.faqs.index', [
            'rows' => $query->paginate(40)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'sort_order', 'label' => 'Sort order', 'direction' => 'asc'],
                ['value' => 'heading', 'label' => 'Heading A–Z', 'direction' => 'asc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.faqs.form');
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::query()->create($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ created.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', ['row' => $faq]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request, $faq));

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ updated.');
    }

    public function toggle(Faq $faq): RedirectResponse
    {
        $faq->is_active = ! $faq->is_active;
        $faq->save();

        return back()->with('status', 'FAQ '.($faq->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('status', 'FAQ deleted.');
    }

    public function importLegacy(Request $request): RedirectResponse
    {
        $fresh = $request->boolean('fresh');

        try {
            $stats = app(\App\Domains\HelpCenter\Services\HelpCenterLegacyImportService::class)->import(
                fresh: $fresh,
                importFaqs: true,
                importTutorials: false,
                copyVideos: false,
                dryRun: false,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Legacy FAQ import failed: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', "Imported {$stats['faqs']} FAQ(s) from legacy.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Faq $existing = null): array
    {
        $data = $request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['heading']);
        }

        $unique = Faq::query()->where('slug', $slug);
        if ($existing !== null) {
            $unique->whereKeyNot($existing->id);
        }
        if ($unique->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        return [
            'heading' => $data['heading'],
            'description' => $data['description'],
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
