<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class CurrentAccount
{
    public static function user(): Authenticatable|null
    {
        $user = auth('web')->user() ?? auth('team')->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    public static function displayName(): string
    {
        $user = self::user();

        if (! $user) {
            return 'Account';
        }

        if ($user instanceof User) {
            $name = trim((string) ($user->first_name ?: $user->name ?: ''));

            if ($name !== '') {
                return $name;
            }
        }

        if ($user instanceof TeamMember) {
            $name = trim(trim((string) $user->first_name).' '.trim((string) $user->last_name));

            if ($name !== '') {
                return $name;
            }
        }

        return (string) ($user->email ?? 'Account');
    }

    public static function email(): ?string
    {
        $user = self::user();

        return $user?->email;
    }

    public static function avatarUrl(): string
    {
        $user = self::user();

        if ($user instanceof User && filled($user->avatar_path)) {
            // Tenant public disk is not served by /storage/... — stream via profile route.
            $path = ltrim(str_replace('\\', '/', (string) $user->avatar_path), '/');

            if (\Illuminate\Support\Facades\Route::has('profile.avatars.show')) {
                return route('profile.avatars.show', ['path' => $path]);
            }

            return url('/profile/avatars/'.$path);
        }

        return asset('images/profile/photo-sample.png');
    }
}
