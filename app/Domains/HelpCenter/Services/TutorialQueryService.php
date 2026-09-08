<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Services;

use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Models\TutorialVideo;
use Illuminate\Support\Collection;

class TutorialQueryService
{
    /**
     * @return Collection<int, TutorialVideo>
     */
    public function active(): Collection
    {
        return HelpCenterCache::remember(
            (string) config('help-center.cache.tutorials'),
            fn (): Collection => TutorialVideo::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        );
    }

    public function findActive(int $id): ?TutorialVideo
    {
        return $this->active()->firstWhere('id', $id);
    }

    /**
     * @return Collection<int, TutorialVideo>
     */
    public function search(string $query): Collection
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return $this->active();
        }

        return $this->active()
            ->filter(function (TutorialVideo $video) use ($query): bool {
                return str_contains(mb_strtolower($video->title), $query)
                    || str_contains(mb_strtolower($video->module_name), $query)
                    || str_contains(mb_strtolower((string) $video->description), $query);
            })
            ->values();
    }
}
