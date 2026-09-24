<?php

declare(strict_types=1);

namespace App\Domains\Integration\Support;

use App\Domains\Integration\Services\PhoneLineService;
use App\Models\WhatsappLine;

/**
 * Gate for number-specific access mode: which routes/nav items stay available.
 */
final class LineContextGate
{
    public static function isActive(): bool
    {
        return PhoneLineService::isLocked();
    }

    public static function lockedLine(): ?WhatsappLine
    {
        if (! self::isActive()) {
            return null;
        }

        return app(PhoneLineService::class)->lockedLine();
    }

    public static function allowsRoute(?string $routeName): bool
    {
        if (! self::isActive()) {
            return true;
        }

        if ($routeName === null || $routeName === '') {
            return true;
        }

        foreach ((array) config('line-context.allowed_route_patterns', []) as $pattern) {
            if (self::matchesPattern($routeName, (string) $pattern)) {
                return true;
            }
        }

        foreach ((array) config('line-context.blocked_route_patterns', []) as $pattern) {
            if (self::matchesPattern($routeName, (string) $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function filterNavItems(array $items): array
    {
        if (! self::isActive()) {
            return $items;
        }

        $hidden = array_flip((array) config('line-context.hidden_nav_routes', []));

        $filtered = [];

        foreach ($items as $item) {
            $route = (string) ($item['route'] ?? '');

            if ($route !== '' && isset($hidden[$route])) {
                continue;
            }

            if ($route !== '' && ! self::allowsRoute($route)) {
                continue;
            }

            if (! empty($item['children']) && is_array($item['children'])) {
                $children = array_values(array_filter(
                    $item['children'],
                    fn (array $child): bool => self::allowsRoute((string) ($child['route'] ?? '')),
                ));

                if ($children === []) {
                    continue;
                }

                $item['children'] = $children;
            }

            $filtered[] = $item;
        }

        foreach ((array) config('line-context.extra_nav_items', []) as $extra) {
            if (! is_array($extra) || blank($extra['route'] ?? null)) {
                continue;
            }

            $already = collect($filtered)->contains(
                fn (array $item): bool => ($item['route'] ?? '') === $extra['route'],
            );

            if (! $already && self::allowsRoute((string) $extra['route'])) {
                $filtered[] = $extra;
            }
        }

        return array_values($filtered);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function filterUserPanelItems(array $items): array
    {
        if (! self::isActive()) {
            return $items;
        }

        $allowed = array_flip((array) config('line-context.allowed_user_panel_routes', []));

        return array_values(array_filter(
            $items,
            fn (array $item): bool => isset($allowed[(string) ($item['route'] ?? '')]),
        ));
    }

    private static function matchesPattern(string $routeName, string $pattern): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        if (! str_contains($pattern, '*')) {
            return $routeName === $pattern;
        }

        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $routeName);
    }
}
