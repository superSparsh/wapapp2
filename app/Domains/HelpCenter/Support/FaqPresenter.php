<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Support;

use App\Models\Faq;
use Illuminate\Support\Collection;

class FaqPresenter
{
    /**
     * @return array<int, array{
     *     number: string,
     *     slug: string,
     *     heading: string,
     *     description: string,
     *     expanded: bool,
     * }>
     */
    public function accordionRows(Collection $faqs, ?string $activeSlug = null): array
    {
        $total = max($faqs->count(), 1);

        return $faqs
            ->values()
            ->map(function (Faq $faq, int $index) use ($activeSlug, $total): array {
                $expanded = $activeSlug !== null
                    ? $faq->slug === $activeSlug
                    : $index === 0;

                return [
                    'number' => str_pad((string) ($index + 1), strlen((string) $total), '0', STR_PAD_LEFT).'.',
                    'slug' => $faq->slug,
                    'heading' => $faq->heading,
                    'description' => (string) $faq->description,
                    'expanded' => $expanded,
                ];
            })
            ->all();
    }
}
