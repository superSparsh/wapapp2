<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\DTOs\LoginResult;
use App\Domains\Auth\Exceptions\InvalidOtpException;
use App\Domains\Auth\Exceptions\OtpDeliveryException;
use App\Domains\Auth\Exceptions\OtpExpiredException;
use App\Domains\Auth\Exceptions\PhoneNotRegisteredException;
use App\Domains\Auth\Services\OtpDelivery\LoginOtpDeliveryManager;
use App\Enums\TenantUserAccountType;
use App\Models\TeamMember;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class LoginOtpService
{
    private const CACHE_PREFIX = 'login_otp:';

    private const COOLDOWN_PREFIX = 'login_otp_cooldown:';

    private const ATTEMPTS_PREFIX = 'login_otp_attempts:';

    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly LoginOtpDeliveryManager $deliveryManager,
        private readonly LoginService $loginService,
    ) {}

    public function send(string $phone): void
    {
        $normalized = $this->requireRegisteredPhone($phone);
        $cooldownKey = self::COOLDOWN_PREFIX.$normalized;

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);

            throw new OtpDeliveryException("Please wait {$seconds} seconds before requesting another OTP.");
        }

        $access = TenantUserAccess::findActiveByPhone($normalized);

        if ($access === null) {
            throw PhoneNotRegisteredException::make();
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$normalized, [
            'otp' => $otp,
            'tenant_id' => $access->tenant_id,
            'email' => $access->email,
            'account_type' => $access->account_type->value,
        ], now()->addSeconds((int) config('login-otp.ttl_seconds', 600)));

        Cache::forget(self::ATTEMPTS_PREFIX.$normalized);

        try {
            $this->deliveryManager->deliver($normalized, $otp, $access);
        } catch (OtpDeliveryException $exception) {
            Cache::forget(self::CACHE_PREFIX.$normalized);

            throw $exception;
        }

        RateLimiter::hit(
            $cooldownKey,
            (int) config('login-otp.resend_cooldown_seconds', 60),
        );
    }

    public function verify(string $phone, string $otp, bool $remember = false): LoginResult
    {
        $normalized = $this->requireRegisteredPhone($phone);
        $payload = Cache::get(self::CACHE_PREFIX.$normalized);

        if (! is_array($payload)) {
            throw OtpExpiredException::make();
        }

        $attemptsKey = self::ATTEMPTS_PREFIX.$normalized;
        $maxAttempts = (int) config('login-otp.max_attempts', 5);

        if ((int) Cache::get($attemptsKey, 0) >= $maxAttempts) {
            Cache::forget(self::CACHE_PREFIX.$normalized);

            throw OtpExpiredException::make();
        }

        if (! hash_equals((string) ($payload['otp'] ?? ''), $otp)) {
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->addMinutes(15));

            throw InvalidOtpException::make();
        }

        Cache::forget(self::CACHE_PREFIX.$normalized);
        Cache::forget($attemptsKey);

        $access = TenantUserAccess::findActiveByPhone($normalized);

        if ($access === null) {
            throw PhoneNotRegisteredException::make();
        }

        $this->tenantResolver->initializeForEmail($access->email);
        $user = $this->resolveAuthenticatable($access);

        return $this->loginService->loginAuthenticated($user, $access, $remember);
    }

    private function requireRegisteredPhone(string $phone): string
    {
        if (! PhoneNormalizer::isValidIndianMobile($phone)) {
            throw new PhoneNotRegisteredException('Please enter a valid 10-digit mobile number.');
        }

        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null || TenantUserAccess::findActiveByPhone($normalized) === null) {
            throw PhoneNotRegisteredException::make();
        }

        return $normalized;
    }

    private function resolveAuthenticatable(TenantUserAccess $access): Authenticatable
    {
        if ($access->account_type === TenantUserAccountType::Team) {
            return TeamMember::query()->where('email', $access->email)->firstOrFail();
        }

        return User::query()->where('email', $access->email)->firstOrFail();
    }
}
