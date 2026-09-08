<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Services;

use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Models\Faq;
use Illuminate\Support\Collection;

class FaqQueryService
{
    /**
     * @return Collection<int, Faq>
     */
    public function active(?string $search = null): Collection
    {
        $faqs = HelpCenterCache::remember(
            (string) config('help-center.cache.faqs'),
            fn (): Collection => Faq::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        );

        $search = mb_strtolower(trim((string) $search));

        if ($search === '') {
            return $faqs;
        }

        return $faqs
            ->filter(function (Faq $faq) use ($search): bool {
                return str_contains(mb_strtolower($faq->heading), $search)
                    || str_contains(mb_strtolower(strip_tags((string) $faq->description)), $search);
            })
            ->values();
    }

    public function findActiveBySlug(string $slug): ?Faq
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return $this->active()->firstWhere('slug', $slug);
    }
}
