<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Models\User;
use App\Models\UserActivation;
use App\Notifications\EmailVerificationOtpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class EmailVerificationService
{
    public function sendActivationCode(User $user): UserActivation
    {
        UserActivation::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->delete();

        $activation = UserActivation::query()->create([
            'user_id' => $user->id,
            'token' => $this->generateToken(),
            'expires_at' => now()->addMinutes(15),
        ]);

        Notification::send($user, new EmailVerificationOtpNotification($activation->token));

        return $activation;
    }

    public function verify(User $user, string $code): bool
    {
        $activation = UserActivation::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($activation === null) {
            return false;
        }

        if ($activation->isExpired()) {
            return false;
        }

        if (! hash_equals($activation->token, $code)) {
            return false;
        }

        $activation->forceFill(['verified_at' => now()])->save();
        $user->forceFill(['email_verified_at' => now()])->save();

        return true;
    }

    private function generateToken(): string
    {
        return (string) random_int(100000, 999999);
    }
}
