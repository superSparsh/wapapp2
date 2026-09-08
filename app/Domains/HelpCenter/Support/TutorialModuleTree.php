<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Support;

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

            if (preg_match('/^(.*?)\s-\sSub-module\s.*?:\s*(.*)$/u', $video->module_name, $matches) === 1) {
                $parent = trim($matches[1]);
                $child = trim($matches[2]);

                $structure[$parent]['type'] = 'parent';
                $structure[$parent]['items'][$child][] = $video;
            } else {
                $structure[$video->module_name]['type'] = 'single';
                $structure[$video->module_name]['items'][] = $video;
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
            'stream_url' => $isLocal ? route('tutorials.stream', ['filename' => basename((string) $video->youtube_id)]) : null,
            'is_local' => $isLocal,
        ];
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
