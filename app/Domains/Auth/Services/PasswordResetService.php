<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Enums\TenantUserAccountType;
use App\Models\TeamMember;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
    ) {}

    public function sendResetLink(string $email): string
    {
        $access = TenantUserAccess::findActiveByEmail($email);

        if ($access === null) {
            return Password::INVALID_USER;
        }

        $this->tenantResolver->initializeForEmail($email);

        $broker = $access->account_type === TenantUserAccountType::Team ? 'team_members' : 'users';
        $user = $access->account_type === TenantUserAccountType::Team
            ? TeamMember::query()->where('email', $email)->first()
            : User::query()->where('email', $email)->first();

        if ($user === null) {
            tenancy()->end();

            return Password::INVALID_USER;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        $user->notify(new ResetPasswordNotification($token));

        tenancy()->end();

        return Password::RESET_LINK_SENT;
    }

    public function reset(string $email, string $password, string $token): string
    {
        $access = TenantUserAccess::findActiveByEmail($email);

        if ($access === null) {
            return Password::INVALID_TOKEN;
        }

        $this->tenantResolver->initializeForEmail($email);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($record === null || ! Hash::check($token, $record->token)) {
            tenancy()->end();

            return Password::INVALID_TOKEN;
        }

        if (now()->diffInMinutes($record->created_at) > config('auth.passwords.users.expire', 60)) {
            tenancy()->end();

            return Password::INVALID_TOKEN;
        }

        if ($access->account_type === TenantUserAccountType::Team) {
            TeamMember::query()->where('email', $email)->update([
                'password' => Hash::make($password),
            ]);
        } else {
            User::query()->where('email', $email)->update([
                'password' => Hash::make($password),
            ]);
        }

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        tenancy()->end();

        return Password::PASSWORD_RESET;
    }
}
