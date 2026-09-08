<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

use App\Enums\TeamMemberRole;
use App\Models\TeamMember;

final class TeamNavigation
{
    /** @return array<int, array<string, mixed>> */
    public static function items(?TeamMember $member = null): array
    {
        $items = config('navigation', []);

        if ($member === null) {
            return $items;
        }

        $items = self::filterByPermissions($items, $member);

        if ($member->role === TeamMemberRole::Manager) {
            $items[] = [
                'label' => 'My Team',
                'route' => 'manager.team.index',
                'icon' => 'users',
                'matches' => [
                    'manager.team.create',
                    'manager.team.edit',
                    'manager.team.roles',
                    'manager.team.import',
                    'manager.settings',
                ],
            ];
        }

        return array_values($items);
    }

    /** @param array<int, array<string, mixed>> $items */
    private static function filterByPermissions(array $items, TeamMember $member): array
    {
        $routePermissions = config('team.route_permissions', []);

        return array_values(array_filter(array_map(
            function (array $item) use ($member, $routePermissions): ?array {
                if (! empty($item['children'])) {
                    $children = array_values(array_filter(
                        $item['children'],
                        fn (array $child): bool => self::canAccessRoute($child['route'] ?? '', $member, $routePermissions),
                    ));

                    if ($children === []) {
                        return null;
                    }

                    $item['children'] = $children;

                    return $item;
                }

                return self::canAccessRoute($item['route'] ?? '', $member, $routePermissions) ? $item : null;
            },
            $items,
        )));
    }

    /** @param array<string, string> $routePermissions */
    private static function canAccessRoute(string $route, TeamMember $member, array $routePermissions): bool
    {
        if ($route === '' || $route === 'dashboard') {
            return false;
        }

        if (str_starts_with($route, 'profile.')) {
            return false;
        }

        $permission = $routePermissions[$route] ?? null;

        if ($permission === null) {
            return false;
        }

        return TeamPermissions::isEnabled($member->permissions, $permission);
    }
}
