<?php

declare(strict_types=1);

namespace App\Domains\Drip\Support;

final class DripNodeCatalog
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function nodeTypesForController(): array
    {
        $grouped = [];

        foreach (config('drip-nodes.types', []) as $type => $meta) {
            $category = $meta['category'] ?? 'Messages';
            $grouped[$category][] = [
                $type,
                $meta['label'] ?? $type,
                $meta['icon'] ?? 'message-notif.svg',
                (bool) ($meta['coming_soon'] ?? false),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    public static function categoriesForController(): array
    {
        return collect(config('drip-nodes.categories', []))
            ->map(function (array $meta, string $label): array {
                return [$label, $meta['icon'] ?? 'message-text.svg', (bool) ($meta['default'] ?? false)];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{type: string, label: string, description: string, icon: string, category: string, comingSoon: bool}>
     */
    public static function flatOptions(): array
    {
        return collect(config('drip-nodes.types', []))
            ->map(function (array $meta, string $type): array {
                return [
                    'type' => $type,
                    'label' => $meta['label'] ?? $type,
                    'description' => $meta['description'] ?? '',
                    'icon' => $meta['icon'] ?? 'message-notif.svg',
                    'category' => $meta['category'] ?? 'Messages',
                    'comingSoon' => (bool) ($meta['coming_soon'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Legacy automation2 "Add an Action" picker options.
     *
     * @return list<array{type: string, label: string, description: string, icon: string, comingSoon: bool}>
     */
    public static function legacyPickerActions(): array
    {
        return collect(config('drip-nodes.legacy_actions', []))
            ->map(function (array $meta, string $type): array {
                return [
                    'type' => $type,
                    'label' => $meta['label'] ?? $type,
                    'description' => $meta['description'] ?? '',
                    'icon' => $meta['icon'] ?? 'message-notif.svg',
                    'comingSoon' => (bool) ($meta['coming_soon'] ?? false),
                ];
            })
            ->values()
            ->all();
    }
}
