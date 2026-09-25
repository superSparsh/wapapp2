<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

final class TeamPermissions
{
    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return config('team.default_permissions', []);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return config('team.permissions', []);
    }

    /** @param array<string, mixed>|null $permissions */
    public static function normalize(?array $permissions): array
    {
        $normalized = self::defaults();

        foreach (array_keys($normalized) as $key) {
            if (! array_key_exists($key, (array) $permissions)) {
                continue;
            }

            $value = $permissions[$key];
            $normalized[$key] = is_bool($value)
                ? $value
                : in_array((string) $value, ['yes', '1', 'true'], true);
        }

        return $normalized;
    }

    /**
     * Module assign (e.g. template_read) grants full CRUD for that module,
     * matching legacy WapApp where only *_read toggles existed.
     */
    public static function isEnabled(?array $permissions, string $key): bool
    {
        $normalized = self::normalize($permissions);

        if ($normalized[$key] ?? false) {
            return true;
        }

        if (preg_match('/^(?P<module>.+)_(?:write|create|update|delete)$/', $key, $matches) === 1) {
            return (bool) ($normalized[$matches['module'].'_read'] ?? false);
        }

        return false;
    }

    /** @param array<string, bool> $input */
    public static function fromInput(array $input): array
    {
        $permissions = self::defaults();

        foreach (array_keys($permissions) as $key) {
            $permissions[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
        }

        return $permissions;
    }
}
