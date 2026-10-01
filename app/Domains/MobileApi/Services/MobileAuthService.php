<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Services;

use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Exceptions\InvalidOtpException;
use App\Domains\Auth\Exceptions\OtpExpiredException;
use App\Domains\Auth\Exceptions\PhoneNotRegisteredException;
use App\Domains\Auth\Services\LoginOtpService;
use App\Domains\Auth\Services\TenantResolver;
use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantSlugService;
use App\Enums\TenantStatus;
use App\Enums\TenantUserAccountType;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class MobileAuthService
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly LoginOtpService $loginOtpService,
        private readonly JwtTokenService $jwt,
        private readonly TenantSlugService $tenantSlugService,
        private readonly TenantDatabaseNamingService $databaseNamingService,
    ) {}

    /**
     * @return array{user: Authenticatable, guard: string, tenant_id: string, tokens: array<string, mixed>}
     */
    public function loginWithPassword(string $email, string $password): array
    {
        $access = $this->tenantResolver->initializeForEmail($email);
        $user = $this->resolveAuthenticatable($access, $email, $password);
        $this->assertActive($user);

        $user->forceFill(['last_login_at' => now()])->save();

        $guard = $access->account_type === TenantUserAccountType::Team ? 'team' : 'web';

        return [
            'user' => $user,
            'guard' => $guard,
            'tenant_id' => $access->tenant_id,
            'tokens' => $this->jwt->issuePair($user, $access->tenant_id, $guard),
        ];
    }

    public function sendOtp(string $phone): void
    {
        $this->loginOtpService->send($phone);
    }

    /**
     * @return array{user: Authenticatable, guard: string, tenant_id: string, tokens: array<string, mixed>}
     */
    public function loginWithOtp(string $phone, string $otp): array
    {
        // Reuse OTP verification without web session: validate then mint JWT.
        $normalized = PhoneNormalizer::normalize($phone) ?? $phone;
        $payload = \Illuminate\Support\Facades\Cache::get('login_otp:'.$normalized);

        if (! is_array($payload)) {
            // LoginOtpService uses PhoneNormalizer for Indian mobiles — try that path.
            try {
                $this->loginOtpService->send($phone);
            } catch (\Throwable) {
                //
            }
            throw OtpExpiredException::make();
        }

        // Delegate to LoginOtpService::verify but it starts a session — use mirror logic.
        $result = $this->verifyOtpWithoutSession($phone, $otp);
        $guard = $result['guard'];
        $user = $result['user'];
        $tenantId = $result['tenant_id'];

        return [
            'user' => $user,
            'guard' => $guard,
            'tenant_id' => $tenantId,
            'tokens' => $this->jwt->issuePair($user, $tenantId, $guard),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, guard: string, tenant_id: string, tokens: array<string, mixed>}
     */
    public function register(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));
        $phone = PhoneNormalizer::normalize((string) $data['phone']) ?? preg_replace('/\D+/', '', (string) $data['phone']);
        $companyName = trim((string) ($data['company_name'] ?? '')) ?: trim($data['first_name'].' '.$data['last_name']);

        if (TenantUserAccess::findActiveByEmail($email) !== null) {
            throw new \InvalidArgumentException('This email is already registered.');
        }

        $plan = Plan::query()->where('is_active', true)->orderBy('sort_order')->first();
        if ($plan === null) {
            throw new \RuntimeException('No active plan available for registration.');
        }

        $tenantSlug = $this->tenantSlugService->generateUnique($companyName ?: $email);

        $tenant = DB::transaction(function () use ($email, $phone, $companyName, $tenantSlug, $plan, $data) {
            $tenant = Tenant::query()->create([
                'id' => $tenantSlug,
                'database_name' => $this->databaseNamingService->forTenantId($tenantSlug),
                'name' => $companyName,
                'company_name' => $companyName,
                'email' => $email,
                'phone' => $phone,
                'status' => TenantStatus::Active,
                'plan_id' => $plan->id,
                'timezone' => (string) ($data['timezone'] ?? 'Asia/Kolkata'),
                'locale' => 'en',
                'country_code' => 'IN',
                'provisioned_at' => now(),
            ]);

            $tenant->domains()->create([
                'domain' => $tenantSlug,
                'is_primary' => true,
            ]);

            TenantUserAccess::query()->updateOrCreate(
                ['email' => $email],
                [
                    'phone' => $phone,
                    'tenant_id' => $tenant->id,
                    'account_type' => TenantUserAccountType::Owner,
                    'is_active' => true,
                    'api_token' => Str::random(60),
                ],
            );

            return $tenant;
        });

        tenancy()->initialize($tenant);

        $user = User::query()->create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $phone,
            'password' => $data['password'],
            'role' => UserRole::Owner,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return [
            'user' => $user,
            'guard' => 'web',
            'tenant_id' => $tenant->id,
            'tokens' => $this->jwt->issuePair($user, $tenant->id, 'web'),
        ];
    }

    /**
     * @return array{user: Authenticatable, guard: string, tenant_id: string, tokens: array<string, mixed>}
     */
    public function refresh(string $refreshToken): array
    {
        $decoded = $this->jwt->decode($refreshToken);

        if (($decoded->type ?? null) !== 'refresh') {
            throw new \InvalidArgumentException('Invalid refresh token');
        }

        $tenantId = (string) ($decoded->tenant_id ?? '');
        $guard = (string) ($decoded->guard ?? 'web');
        $userId = (int) ($decoded->user_id ?? 0);

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }

        tenancy()->initialize($tenant);

        $user = $guard === 'team'
            ? TeamMember::query()->find($userId)
            : User::query()->find($userId);

        if ($user === null) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }

        $this->assertActive($user);

        return [
            'user' => $user,
            'guard' => $guard,
            'tenant_id' => $tenantId,
            'tokens' => $this->jwt->issuePair($user, $tenantId, $guard),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(Authenticatable $user, string $tenantId): array
    {
        $tenant = Tenant::query()->find($tenantId);
        $name = (string) ($user->name ?? '');
        $first = (string) ($user->first_name ?? '');
        $last = (string) ($user->last_name ?? '');

        if ($first === '' && $name !== '') {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }

        $wallet = 0.0;
        $validUntil = null;
        $remainingDays = null;
        $planName = null;
        try {
            if (tenancy()->initialized || $tenantId !== '') {
                if (! tenancy()->initialized && $tenant !== null) {
                    tenancy()->initialize($tenant);
                }
                $wallet = round(app(\App\Domains\Billing\Services\WalletService::class)->balance(), 2);
                if ($wallet <= 0) {
                    $wallet = round((float) (\App\Models\WalletAccount::query()->value('balance') ?? 0), 2);
                }
                $summary = app(\App\Domains\Billing\Services\SubscriptionService::class)->subscriptionSummary();
                $planName = $summary['plan_name'] ?? $summary['plan']?->name;
                $expiresAt = $summary['expires_at'] ?? null;
                if ($expiresAt instanceof \Illuminate\Support\Carbon) {
                    $validUntil = $expiresAt->toDateString();
                    $remainingDays = (int) floor((float) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false));
                }
            }
        } catch (\Throwable) {
            //
        }

        return [
            'id' => (int) $user->getAuthIdentifier(),
            'uid' => (string) ($user->uuid ?? $user->getAuthIdentifier()),
            'first_name' => $first,
            'last_name' => $last,
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'timezone' => (string) ($tenant?->timezone ?? 'Asia/Kolkata'),
            'activated' => $user instanceof User ? (bool) $user->is_active : true,
            'status' => $user instanceof User
                ? ($user->is_active ? 'active' : 'inactive')
                : (string) ($user->status->value ?? 'active'),
            'wallet_amount' => $wallet,
            'wallet_balance' => $wallet,
            'plan_name' => $planName,
            'valid_until' => $validUntil,
            'remaining_days' => $remainingDays,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{user: Authenticatable, guard: string, tenant_id: string}
     */
    private function verifyOtpWithoutSession(string $phone, string $otp): array
    {
        // Call LoginOtpService verify which creates a session — then clear session auth keys.
        $loginResult = $this->loginOtpService->verify($phone, $otp, false);

        // Drop session auth so JWT remains the sole mobile auth mechanism.
        session()->flush();
        Auth::forgetGuards();

        return [
            'user' => $loginResult->user,
            'guard' => $loginResult->guard,
            'tenant_id' => $loginResult->tenantId,
        ];
    }

    private function resolveAuthenticatable(
        TenantUserAccess $access,
        string $email,
        string $password,
    ): Authenticatable {
        if ($access->account_type === TenantUserAccountType::Team) {
            $member = TeamMember::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->first();

            if ($member === null || ! Hash::check($password, $member->password)) {
                throw InvalidCredentialsException::make();
            }

            return $member;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw InvalidCredentialsException::make();
        }

        return $user;
    }

    private function assertActive(Authenticatable $user): void
    {
        if ($user instanceof User && ! $user->is_active) {
            throw AccountInactiveException::make();
        }

        if ($user instanceof TeamMember && $user->status->value !== 'active') {
            throw AccountInactiveException::make();
        }
    }
}
