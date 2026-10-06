<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\HelpCenter\Services\HelpCenterLegacyImportService;
use App\Domains\HelpCenter\Services\TutorialVideoStorage;
use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Domains\HelpCenter\Support\TutorialModuleTree;
use App\Http\Controllers\Controller;
use App\Models\TutorialVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTutorialController extends Controller
{
    public function __construct(
        private readonly TutorialVideoStorage $videoStorage,
        private readonly TutorialModuleTree $moduleTree,
    ) {}

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

        $rows = $query->paginate(40)->withQueryString();

        $fileStatus = [];
        foreach ($rows as $row) {
            $fileStatus[$row->id] = $row->isLocalFile() && $this->videoStorage->exists((string) $row->youtube_id);
        }

        return view('admin.tutorials.index', [
            'rows' => $rows,
            'fileStatus' => $fileStatus,
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
        return view('admin.tutorials.form', [
            'hasVideoFile' => false,
            'playbackUrl' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, requireVideo: true);
        $previousFilename = null;

        if ($request->hasFile('video')) {
            $data['youtube_id'] = $this->videoStorage->store(
                $request->file('video'),
                $data['youtube_id'] ?: null,
                $previousFilename,
            );
        }

        TutorialVideo::query()->create($data);
        HelpCenterCache::flush();

        return redirect()->route('admin.tutorials.index')->with('status', 'Tutorial created.');
    }

    public function edit(TutorialVideo $tutorial): View
    {
        $hasVideoFile = $tutorial->isLocalFile() && $this->videoStorage->exists((string) $tutorial->youtube_id);

        return view('admin.tutorials.form', [
            'row' => $tutorial,
            'hasVideoFile' => $hasVideoFile,
            'playbackUrl' => $hasVideoFile
                ? $this->moduleTree->localPlaybackUrl((string) $tutorial->youtube_id)
                : null,
        ]);
    }

    public function update(Request $request, TutorialVideo $tutorial): RedirectResponse
    {
        $data = $this->validated($request, requireVideo: false);
        $previousFilename = (string) $tutorial->youtube_id;

        if ($request->hasFile('video')) {
            $data['youtube_id'] = $this->videoStorage->store(
                $request->file('video'),
                $data['youtube_id'] ?: $previousFilename,
                $previousFilename,
            );
        } elseif (
            $data['youtube_id'] !== $previousFilename
            && $tutorial->isLocalFile()
            && $this->videoStorage->exists($previousFilename)
        ) {
            // Filename renamed in the form without a new upload — move/rename on disk.
            $oldPath = $this->videoStorage->pathFor($previousFilename);
            $newPath = $this->videoStorage->pathFor($data['youtube_id']);
            if (is_file($oldPath) && $oldPath !== $newPath) {
                @rename($oldPath, $newPath);
            }
        }

        $tutorial->update($data);
        HelpCenterCache::flush();

        $message = $request->hasFile('video')
            ? 'Tutorial updated and video replaced.'
            : 'Tutorial updated.';

        return redirect()->route('admin.tutorials.index')->with('status', $message);
    }

    public function destroyVideo(TutorialVideo $tutorial): RedirectResponse
    {
        if ($tutorial->isLocalFile()) {
            $this->videoStorage->delete((string) $tutorial->youtube_id);
        }

        HelpCenterCache::flush();

        return back()->with('status', 'Video file deleted. Upload a new file to replace it.');
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
        if ($tutorial->isLocalFile()) {
            $this->videoStorage->delete((string) $tutorial->youtube_id);
        }

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
    private function validated(Request $request, bool $requireVideo): array
    {
        $hasUpload = $request->hasFile('video');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'module_name' => ['required', 'string', 'max:255'],
            'youtube_id' => [
                Rule::requiredIf(! $hasUpload),
                'nullable',
                'string',
                'max:191',
            ],
            'video' => [
                Rule::requiredIf($requireVideo && ! $request->filled('youtube_id')),
                'nullable',
                'file',
                'mimes:mp4,webm,ogg,mov',
                'max:204800', // 200 MB
            ],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'title' => $data['title'],
            'module_name' => $data['module_name'],
            'youtube_id' => trim((string) ($data['youtube_id'] ?? '')),
            'description' => $data['description'] ?? null,
            'duration' => $data['duration'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
