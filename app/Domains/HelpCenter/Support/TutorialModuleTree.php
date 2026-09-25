<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Support;

use App\Models\TutorialVideo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

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
        /** @var array<string, array{sort: int, type: string, items: mixed}> $structure */
        $structure = [];

        foreach ($videos as $video) {
            if ($search !== '' && ! $this->matchesSearch($video, $search)) {
                continue;
            }

            $parsed = $this->parseModuleName((string) $video->module_name);
            $parent = $parsed['parent'];
            $child = $parsed['child'];
            $sort = $parsed['sort'];

            if ($child !== null) {
                if (($structure[$parent]['type'] ?? null) === 'single') {
                    $existing = $structure[$parent]['items'] ?? [];
                    $structure[$parent]['items'] = ['_general' => $existing];
                }

                $structure[$parent]['type'] = 'parent';
                $structure[$parent]['sort'] = min($sort, (int) ($structure[$parent]['sort'] ?? $sort));
                $structure[$parent]['items'][$child][] = $video;
            } else {
                // Keep existing parent buckets if a submodule already created this label.
                if (($structure[$parent]['type'] ?? null) === 'parent') {
                    $structure[$parent]['items']['_general'][] = $video;
                    $structure[$parent]['sort'] = min($sort, (int) ($structure[$parent]['sort'] ?? $sort));
                } else {
                    $structure[$parent]['type'] = 'single';
                    $structure[$parent]['sort'] = min($sort, (int) ($structure[$parent]['sort'] ?? $sort));
                    $structure[$parent]['items'][] = $video;
                }
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
                        'label' => $childLabel === '_general' ? 'General' : (string) $childLabel,
                        'videos' => $mappedVideos,
                        'sort' => (int) ($data['sort'] ?? 999),
                    ];
                }

                if ($children === [] && $search !== '' && ! str_contains(mb_strtolower($label), $search)) {
                    continue;
                }

                $categories[] = [
                    'label' => (string) $label,
                    'sort' => (int) ($data['sort'] ?? 999),
                    'expanded' => $this->moduleExpanded($children, $activeVideoId),
                    'children' => array_map(static function (array $child): array {
                        unset($child['sort']);

                        return $child;
                    }, $children),
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
                'sort' => (int) ($data['sort'] ?? 999),
                'expanded' => $this->videosContainActive($mappedVideos, $activeVideoId),
                'children' => null,
                'videos' => $mappedVideos,
            ];
        }

        usort($categories, static function (array $a, array $b): int {
            $sortCmp = ($a['sort'] ?? 999) <=> ($b['sort'] ?? 999);
            if ($sortCmp !== 0) {
                return $sortCmp;
            }

            return strcasecmp((string) $a['label'], (string) $b['label']);
        });

        $categories = array_map(static function (array $category): array {
            unset($category['sort']);

            return $category;
        }, $categories);

        if ($categories !== [] && ! collect($categories)->contains(fn (array $category): bool => $category['expanded'])) {
            $categories[0]['expanded'] = true;
        }

        return array_values($categories);
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
        $index = collect($flatVideos)->search(fn (array $video): bool => $video['id'] === $activeVideoId);
        $index = $index === false ? 0 : (int) $index;

        return [
            'index' => $index + 1,
            'total' => count($flatVideos),
            'previous_id' => $flatVideos[$index - 1]['id'] ?? null,
            'next_id' => $flatVideos[$index + 1]['id'] ?? null,
        ];
    }

    /**
     * @return array{id: int, title: string, module_name: string, description: string|null, duration: string|null, active: bool, url: string, embed_url: string|null, stream_url: string|null, is_local: bool}
     */
    public function videoRow(TutorialVideo $video, ?int $activeVideoId): array
    {
        $isLocal = $video->isLocalFile();

        return [
            'id' => $video->id,
            'title' => $video->title,
            'module_name' => $video->module_name,
            'description' => $video->description,
            'duration' => $video->duration,
            'active' => $activeVideoId === $video->id,
            'url' => route('tutorials.index', ['video_id' => $video->id]),
            'embed_url' => $isLocal ? null : $this->youtubeEmbedUrl((string) $video->youtube_id),
            'stream_url' => $isLocal ? $this->localPlaybackUrl((string) $video->youtube_id) : null,
            'is_local' => $isLocal,
        ];
    }

    /**
     * Prefer a direct public asset URL so nginx/apache can serve MP4s reliably.
     * Fall back to the Laravel stream route when the file is missing locally
     * (e.g. during tests) or only available via the stream handler.
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
        $filename = basename($filename);
        $directory = (string) config('help-center.video_path');
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (File::isFile($path)) {
            return $path;
        }

        if (! File::isDirectory($directory)) {
            return null;
        }

        foreach (File::files($directory) as $file) {
            if (strcasecmp($file->getFilename(), $filename) === 0) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * @return array{sort: int, parent: string, child: string|null}
     */
    public function parseModuleName(string $moduleName): array
    {
        $moduleName = trim($moduleName);

        if (preg_match('/^Module\s+(\d+)\s*:\s*(.+)$/ui', $moduleName, $matches) === 1) {
            $number = (int) $matches[1];
            $rest = trim($matches[2]);

            if (preg_match('/^(.*?)\s-\sSub-module\s+\d+\s*:\s*(.*)$/ui', $rest, $sub) === 1) {
                return [
                    'sort' => $number,
                    'parent' => $this->humanizeModuleLabel(trim($sub[1])),
                    'child' => trim($sub[2]),
                ];
            }

            return [
                'sort' => $number,
                'parent' => $this->humanizeModuleLabel($rest),
                'child' => null,
            ];
        }

        if (preg_match('/^(.*?)\s-\sSub-module\s.*?:\s*(.*)$/u', $moduleName, $matches) === 1) {
            return [
                'sort' => 999,
                'parent' => $this->humanizeModuleLabel(trim($matches[1])),
                'child' => trim($matches[2]),
            ];
        }

        return [
            'sort' => 999,
            'parent' => $this->humanizeModuleLabel($moduleName),
            'child' => null,
        ];
    }

    private function humanizeModuleLabel(string $label): string
    {
        $label = trim($label);

        if ($label !== '' && $label === mb_strtoupper($label)) {
            return mb_convert_case(mb_strtolower($label), MB_CASE_TITLE, 'UTF-8');
        }

        return $label;
    }

    private function encodePathSegment(string $filename): string
    {
        return rawurlencode($filename);
    }

    private function youtubeEmbedUrl(string $youtubeId): string
    {
        if ($youtubeId === 'PLACEHOLDER') {
            return 'https://www.youtube.com/embed/dQw4w9WgXcQ';
        }

        return 'https://www.youtube.com/embed/'.$youtubeId;
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
