<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\HelpCenter\Services\HelpCenterLegacyImportService;
use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Http\Controllers\Controller;
use App\Models\TutorialVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTutorialController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['sort_order', 'title', 'module_name', 'id'],
            defaultSort: 'sort_order',
            defaultDirection: 'asc',
        );

        $query = TutorialVideo::query();
        AdminListQuery::applySearch($query, $parsed['q'], ['title', 'module_name', 'youtube_id', 'description']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'sort_order' => 'sort_order',
                'title' => 'title',
                'module_name' => 'module_name',
                'id' => 'id',
            ],
            'sort_order',
        );

        return view('admin.tutorials.index', [
            'rows' => $query->paginate(40)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'sort_order', 'label' => 'Sort order', 'direction' => 'asc'],
                ['value' => 'title', 'label' => 'Title A–Z', 'direction' => 'asc'],
                ['value' => 'module_name', 'label' => 'Module A–Z', 'direction' => 'asc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.tutorials.form');
    }

    public function store(Request $request): RedirectResponse
    {
        TutorialVideo::query()->create($this->validated($request));
        HelpCenterCache::flush();

        return redirect()->route('admin.tutorials.index')->with('status', 'Tutorial created.');
    }

    public function edit(TutorialVideo $tutorial): View
    {
        return view('admin.tutorials.form', ['row' => $tutorial]);
    }

    public function update(Request $request, TutorialVideo $tutorial): RedirectResponse
    {
        $tutorial->update($this->validated($request));
        HelpCenterCache::flush();

        return redirect()->route('admin.tutorials.index')->with('status', 'Tutorial updated.');
    }

    public function toggle(TutorialVideo $tutorial): RedirectResponse
    {
        $tutorial->is_active = ! $tutorial->is_active;
        $tutorial->save();
        HelpCenterCache::flush();

        return back()->with('status', 'Tutorial '.($tutorial->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(TutorialVideo $tutorial): RedirectResponse
    {
        $tutorial->delete();
        HelpCenterCache::flush();

        return back()->with('status', 'Tutorial deleted.');
    }

    public function importLegacy(Request $request): RedirectResponse
    {
        $fresh = $request->boolean('fresh');
        $copyVideos = $request->boolean('copy_videos');

        try {
            $stats = app(HelpCenterLegacyImportService::class)->import(
                fresh: $fresh,
                importFaqs: false,
                importTutorials: true,
                copyVideos: $copyVideos,
                dryRun: false,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Legacy tutorial import failed: '.$e->getMessage());
        }

        $message = "Imported {$stats['tutorials']} tutorial(s) from legacy.";
        if ($copyVideos) {
            $message .= " Copied {$stats['videos_copied']} video file(s).";
        }

        return redirect()
            ->route('admin.tutorials.index')
            ->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'module_name' => ['required', 'string', 'max:255'],
            'youtube_id' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'title' => $data['title'],
            'module_name' => $data['module_name'],
            'youtube_id' => $data['youtube_id'],
            'description' => $data['description'] ?? null,
            'duration' => $data['duration'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
