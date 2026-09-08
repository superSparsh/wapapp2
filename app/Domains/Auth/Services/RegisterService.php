<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantSlugService;
use App\Enums\TenantStatus;
use App\Enums\TenantUserAccountType;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\SignupConsent;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterService
{
    public function __construct(
        private readonly SignupSessionService $signupSession,
        private readonly EmailVerificationService $emailVerification,
        private readonly TenantSlugService $tenantSlugService,
        private readonly TenantDatabaseNamingService $databaseNamingService,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function register(array $data): User
    {
        if (! $this->signupSession->hasCompletedPreRegistration()) {
            throw new \RuntimeException('Please complete all signup steps before registering.');
        }

        $plan = Plan::query()->where('is_active', true)->orderBy('sort_order')->first();

        if ($plan === null) {
            throw new \RuntimeException('No active plan available for registration.');
        }

        $email = strtolower($data['work_email']);
        $companyName = $data['business_name'] ?? $data['first_name'].' '.$data['last_name'];
        $tenantSlug = $this->tenantSlugService->generateUnique($data['business_name'] ?? $email);

        if (TenantUserAccess::findActiveByEmail($email) !== null) {
            throw new \InvalidArgumentException('This email is already registered.');
        }

        $tenant = DB::transaction(function () use ($data, $email, $companyName, $tenantSlug, $plan) {
            $tenant = Tenant::query()->create([
                'id' => $tenantSlug,
                'database_name' => $this->databaseNamingService->forTenantId($tenantSlug),
                'name' => $companyName,
                'company_name' => $companyName,
                'email' => $email,
                'phone' => $data['mobile'] ?? null,
                'status' => TenantStatus::Active,
                'plan_id' => $plan->id,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'country_code' => $data['country_code'] ?? 'IN',
                'provisioned_at' => now(),
            ]);

            $tenant->domains()->create([
                'domain' => $tenantSlug,
                'is_primary' => true,
            ]);

            return $tenant;
        });

        tenancy()->initialize($tenant);

        $user = User::query()->create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $email,
            'phone' => $data['mobile'] ?? null,
            'password' => $data['new_password'],
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);

        $this->persistConsents($user);
        $this->syncTenantAccess($tenant->id, $email, $data['mobile'] ?? null);

        tenancy()->end();

        $this->signupSession->forget();

        tenancy()->initialize($tenant);
        $this->emailVerification->sendActivationCode($user);
        tenancy()->end();

        return $user;
    }

    private function persistConsents(User $user): void
    {
        $consents = $this->signupSession->all();
        $mapping = [
            1 => 'meta_access',
            2 => 'has_website',
            3 => 'business_terms',
            4 => 'commerce_policy',
            5 => 'manager_verified',
        ];

        foreach ($mapping as $step => $key) {
            if (! isset($consents[$key])) {
                continue;
            }

            SignupConsent::query()->create([
                'user_id' => $user->id,
                'step' => $step,
                'question_key' => $key,
                'answer' => (string) $consents[$key],
                'consented_at' => now(),
            ]);
        }
    }

    private function syncTenantAccess(string $tenantId, string $email, ?string $phone = null): void
    {
        tenancy()->central(function () use ($tenantId, $email, $phone): void {
            TenantUserAccess::query()->create([
                'email' => strtolower($email),
                'phone' => $phone !== null ? \App\Support\PhoneNormalizer::normalize($phone) : null,
                'tenant_id' => $tenantId,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
            ]);
        });
    }
}
