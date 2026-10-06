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
            $playback = $this->moduleTree->resolvePlayback($row);
            $fileStatus[$row->id] = [
                'has_file' => $playback['path'] !== null,
                'is_updated' => $row->video_updated_at !== null && ! $playback['using_fallback'],
                'using_fallback' => $playback['using_fallback'],
                'playback_filename' => $playback['filename'],
            ];
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
            $data['video_updated_at'] = now();
        }

        TutorialVideo::query()->create($data);
        HelpCenterCache::flush();

        return redirect()->route('admin.tutorials.index')->with('status', 'Tutorial created.');
    }

    public function edit(TutorialVideo $tutorial): View
    {
        $playback = $this->moduleTree->resolvePlayback($tutorial);
        $hasVideoFile = $playback['path'] !== null;

        return view('admin.tutorials.form', [
            'row' => $tutorial,
            'hasVideoFile' => $hasVideoFile,
            'usingFallback' => $playback['using_fallback'],
            'playbackFilename' => $playback['filename'],
            'playbackUrl' => $hasVideoFile && $playback['filename'] !== null
                ? $this->moduleTree->localPlaybackUrl($playback['filename'])
                : null,
        ]);
    }

    public function update(Request $request, TutorialVideo $tutorial): RedirectResponse
    {
        $data = $this->validated($request, requireVideo: false);
        $previousFilename = trim((string) $tutorial->youtube_id);

        if ($request->hasFile('video')) {
            // Prefer the form filename, else keep the previous DB name so replace stays stable.
            $preferred = $data['youtube_id'] !== '' ? $data['youtube_id'] : $previousFilename;
            $stored = $this->videoStorage->store(
                $request->file('video'),
                $preferred !== '' ? $preferred : null,
                $previousFilename !== '' ? $previousFilename : null,
            );
            $data['youtube_id'] = $stored;

            if (
                $previousFilename !== ''
                && strcasecmp($previousFilename, $stored) !== 0
            ) {
                // Keep old filename as fallback so front can still play it
                // until the new file is confirmed, or for other tutorials.
                $data['previous_youtube_id'] = $previousFilename;
            }

            $data['video_updated_at'] = now();
        } elseif ($data['youtube_id'] !== '' && $data['youtube_id'] !== $previousFilename) {
            $newPath = $this->videoStorage->pathFor($data['youtube_id']);
            $oldResolved = $previousFilename !== '' ? $this->videoStorage->resolvePath($previousFilename) : null;

            if ($oldResolved !== null && ! is_file($newPath)) {
                // Rename on disk when the old file still exists.
                @rename($oldResolved, $newPath);
            } elseif (
                $previousFilename !== ''
                && $this->videoStorage->exists($previousFilename)
            ) {
                // Pointing at a new file that already exists — keep old as fallback.
                $data['previous_youtube_id'] = $previousFilename;
            }

            if ($this->videoStorage->exists($data['youtube_id'])) {
                $data['video_updated_at'] = now();
            }
        } elseif ($data['youtube_id'] === '' && $previousFilename !== '') {
            // Keep previous local filename when the field is left blank.
            $data['youtube_id'] = $previousFilename;
        }

        // Normalize casing to the real on-disk basename when a match exists.
        if ($data['youtube_id'] !== '') {
            $canonical = $this->videoStorage->canonicalFilename($data['youtube_id']);
            if ($canonical !== null) {
                $data['youtube_id'] = $canonical;
            }
        }

        $tutorial->update($data);
        HelpCenterCache::flush();
        $tutorial->refresh();

        $playback = $this->moduleTree->resolvePlayback($tutorial);

        $message = $request->hasFile('video')
            ? 'Tutorial updated and video replaced.'
            : 'Tutorial updated.';

        if ($playback['path'] === null) {
            $message .= ' Warning: no playable file on disk yet for '.$tutorial->youtube_id
                .' — upload the MP4 or keep the previous filename available as fallback.';
        } elseif ($playback['using_fallback']) {
            $message .= ' Playing previous file ('.$playback['filename'].') until the new filename is on disk.';
        }

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
