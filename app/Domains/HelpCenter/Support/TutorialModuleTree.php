<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Support;

use App\Domains\HelpCenter\Services\TutorialVideoStorage;
use App\Models\TutorialVideo;
use Illuminate\Support\Collection;

class TutorialModuleTree
{
    /**
     * @param  Collection<int, TutorialVideo>  $videos
     * @return array<int, array{
     *     label: string,
     *     expanded: bool,
     *     children: array<int, array{label: string, videos: array<int, array<string, mixed>>}>|null,
     *     videos: array<int, array<string, mixed>>|null,
     * }>
     */
    public function build(Collection $videos, ?int $activeVideoId = null, ?string $search = null): array
    {
        $search = mb_strtolower(trim((string) $search));
        $structure = [];

        foreach ($videos as $video) {
            if ($search !== '' && ! $this->matchesSearch($video, $search)) {
                continue;
            }

            $moduleName = trim((string) $video->module_name);

            // Legacy layout: "Module 3: Automation - Sub-module 1: Chatbot"
            // → parent "Module 3: Automation", child "Chatbot"
            if (preg_match('/^(.*?)\s-\sSub-module\s.*?:\s*(.*)$/u', $moduleName, $matches) === 1) {
                $parent = trim($matches[1]);
                $child = trim($matches[2]);

                $structure[$parent]['type'] = 'parent';
                $structure[$parent]['items'][$child][] = $video;
            } else {
                $structure[$moduleName]['type'] = 'single';
                $structure[$moduleName]['items'][] = $video;
            }
        }

        $categories = [];

        foreach ($structure as $label => $data) {
            if (($data['type'] ?? 'single') === 'parent') {
                $children = [];

                foreach ($data['items'] as $childLabel => $childVideos) {
                    $mappedVideos = collect($childVideos)
                        ->map(fn (TutorialVideo $video): array => $this->videoRow($video, $activeVideoId))
                        ->values()
                        ->all();

                    if ($mappedVideos === []) {
                        continue;
                    }

                    $children[] = [
                        'label' => (string) $childLabel,
                        'videos' => $mappedVideos,
                    ];
                }

                if ($children === [] && $search !== '' && ! str_contains(mb_strtolower($label), $search)) {
                    continue;
                }

                $categories[] = [
                    'label' => (string) $label,
                    'expanded' => $this->moduleExpanded($children, $activeVideoId),
                    'children' => $children,
                    'videos' => null,
                ];

                continue;
            }

            $mappedVideos = collect($data['items'] ?? [])
                ->map(fn (TutorialVideo $video): array => $this->videoRow($video, $activeVideoId))
                ->values()
                ->all();

            if ($mappedVideos === [] && $search !== '' && ! str_contains(mb_strtolower($label), $search)) {
                continue;
            }

            $categories[] = [
                'label' => (string) $label,
                'expanded' => $this->videosContainActive($mappedVideos, $activeVideoId),
                'children' => null,
                'videos' => $mappedVideos,
            ];
        }

        if ($categories !== [] && ! collect($categories)->contains(fn (array $category): bool => $category['expanded'])) {
            $categories[0]['expanded'] = true;
        }

        return $categories;
    }

    /**
     * @param  Collection<int, TutorialVideo>  $videos
     * @return array<int, array<string, mixed>>
     */
    public function flat(Collection $videos): array
    {
        return $videos
            ->map(fn (TutorialVideo $video): array => $this->videoRow($video, null))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $flatVideos
     * @return array{index: int, total: int, previous_id: int|null, next_id: int|null}
     */
    public function navigation(array $flatVideos, ?int $activeVideoId): array
    {
        $activeVideoId = $activeVideoId !== null ? (int) $activeVideoId : null;
        $index = collect($flatVideos)->search(
            fn (array $video): bool => (int) $video['id'] === $activeVideoId,
        );
        $index = $index === false ? 0 : (int) $index;

        $previousId = $flatVideos[$index - 1]['id'] ?? null;
        $nextId = $flatVideos[$index + 1]['id'] ?? null;

        return [
            'index' => $index + 1,
            'total' => count($flatVideos),
            'previous_id' => $previousId !== null ? (int) $previousId : null,
            'next_id' => $nextId !== null ? (int) $nextId : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     module_name: string,
     *     description: string|null,
     *     duration: string|null,
     *     active: bool,
     *     url: string,
     *     embed_url: null,
     *     stream_url: string|null,
     *     is_local: bool,
     *     has_file: bool,
     *     is_updated: bool,
     *     using_fallback: bool,
     *     playback_filename: string|null,
     *     cache_buster: string
     * }
     */
    public function videoRow(TutorialVideo $video, ?int $activeVideoId): array
    {
        $playback = $this->resolvePlayback($video);
        $hasLocalFile = $playback['path'] !== null;
        $isUpdated = $video->video_updated_at !== null && ! $playback['using_fallback'];

        return [
            'id' => (int) $video->id,
            'title' => $video->title,
            'module_name' => $video->module_name,
            'description' => $video->description,
            'duration' => $video->duration,
            'active' => $activeVideoId !== null && (int) $activeVideoId === (int) $video->id,
            'url' => route('tutorials.index', ['video_id' => (int) $video->id]),
            'embed_url' => null,
            'stream_url' => $hasLocalFile && $playback['filename'] !== null
                ? $this->localPlaybackUrl($playback['filename'])
                : null,
            'is_local' => $video->isLocalFile(),
            'has_file' => $hasLocalFile,
            'is_updated' => $isUpdated,
            'using_fallback' => $playback['using_fallback'],
            'playback_filename' => $playback['filename'],
            'cache_buster' => $playback['cache_buster'],
        ];
    }

    /**
     * Prefer current (updated) file; fall back to previous/legacy file if new one is missing.
     *
     * @return array{filename: string|null, path: string|null, using_fallback: bool, cache_buster: string}
     */
    public function resolvePlayback(TutorialVideo $video): array
    {
        $storage = app(TutorialVideoStorage::class);
        $candidates = $video->candidateFilenames();
        $primary = $candidates[0] ?? null;

        foreach ($candidates as $index => $filename) {
            $path = $storage->resolvePath($filename);
            if ($path === null) {
                continue;
            }

            $mtime = @filemtime($path);

            return [
                'filename' => basename($path),
                'path' => $path,
                'using_fallback' => $primary !== null && strcasecmp($filename, $primary) !== 0,
                'cache_buster' => (string) ($mtime !== false ? $mtime : $video->id),
            ];
        }

        return [
            'filename' => $primary,
            'path' => null,
            'using_fallback' => false,
            'cache_buster' => (string) ($video->updated_at?->timestamp ?? $video->id),
        ];
    }

    /**
     * Prefer a direct public asset URL so nginx/apache can serve MP4s reliably.
     */
    public function localPlaybackUrl(string $youtubeId): string
    {
        $filename = basename($youtubeId);
        $resolved = $this->resolveLocalVideoPath($filename);

        if ($resolved !== null) {
            return asset('assets/videos/tutorials/'.$this->encodePathSegment(basename($resolved)));
        }

        return route('tutorials.stream', ['filename' => $filename]);
    }

    public function resolveLocalVideoPath(string $filename): ?string
    {
        return app(TutorialVideoStorage::class)->resolvePath($filename);
    }

    private function encodePathSegment(string $filename): string
    {
        return rawurlencode($filename);
    }

    private function matchesSearch(TutorialVideo $video, string $search): bool
    {
        return str_contains(mb_strtolower($video->title), $search)
            || str_contains(mb_strtolower($video->module_name), $search)
            || str_contains(mb_strtolower((string) $video->description), $search);
    }

    /**
     * @param  array<int, array{label: string, videos: array<int, array<string, mixed>>}>  $children
     */
    private function moduleExpanded(array $children, ?int $activeVideoId): bool
    {
        foreach ($children as $child) {
            if ($this->videosContainActive($child['videos'], $activeVideoId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $videos
     */
    private function videosContainActive(array $videos, ?int $activeVideoId): bool
    {
        if ($activeVideoId === null) {
            return false;
        }

        return collect($videos)->contains(fn (array $video): bool => $video['id'] === $activeVideoId);
    }
}
