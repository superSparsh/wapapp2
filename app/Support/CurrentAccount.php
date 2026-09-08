<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;

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

        if ($user instanceof User && $user->avatar_path) {
            return Storage::disk((string) config('account.avatar.disk', 'public'))
                ->url($user->avatar_path);
        }

        return asset('images/profile/photo-sample.png');
    }
}
