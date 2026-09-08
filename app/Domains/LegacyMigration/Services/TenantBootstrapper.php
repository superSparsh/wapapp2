<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantProvisioner;
use App\Domains\Tenancy\Services\TenantSlugService;
use App\Enums\TenantStatus;
use App\Enums\TenantUserAccountType;
use App\Models\LegacyCustomerMigration;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Support\PhoneNormalizer;
use RuntimeException;

class TenantBootstrapper
{
    public function __construct(
        private readonly TenantSlugService $slugService,
        private readonly TenantDatabaseNamingService $namingService,
        private readonly TenantProvisioner $provisioner,
    ) {}

    /**
     * Find or create a tenant for this legacy customer (no duplicates).
     *
     * @return array{tenant: Tenant, created: bool, reused_reason: ?string}
     */
    public function resolveTenant(LegacyCustomerSnapshot $customer, bool $force = false): array
    {
        return tenancy()->central(function () use ($customer, $force) {
            $existingMigration = LegacyCustomerMigration::query()
                ->where('legacy_customer_id', $customer->id)
                ->first();

            if ($existingMigration?->tenant_id && $existingMigration->status === 'completed' && ! $force) {
                $tenant = Tenant::query()->find($existingMigration->tenant_id);
                if ($tenant !== null) {
                    return [
                        'tenant' => $tenant,
                        'created' => false,
                        'reused_reason' => 'legacy_migration_record',
                    ];
                }
            }

            if ($customer->email) {
                $access = TenantUserAccess::findActiveByEmail($customer->email);
                if ($access !== null) {
                    $tenant = Tenant::query()->find($access->tenant_id);
                    if ($tenant !== null) {
                        $this->attachLegacyMetadata($tenant, $customer);
                        $this->provisioner->ensureDatabase($tenant);

                        return [
                            'tenant' => $tenant->refresh(),
                            'created' => false,
                            'reused_reason' => 'tenant_user_access_email',
                        ];
                    }
                }

                $tenantByEmail = Tenant::query()
                    ->whereRaw('LOWER(email) = ?', [strtolower($customer->email)])
                    ->first();

                if ($tenantByEmail !== null) {
                    $this->attachLegacyMetadata($tenantByEmail, $customer);
                    $this->provisioner->ensureDatabase($tenantByEmail);

                    return [
                        'tenant' => $tenantByEmail->refresh(),
                        'created' => false,
                        'reused_reason' => 'tenants_email_unique',
                    ];
                }
            }

            $tenantByLegacyId = Tenant::query()
                ->get()
                ->first(fn (Tenant $tenant) => (int) data_get($tenant->settings, 'legacy_customer_id') === $customer->id);

            if ($tenantByLegacyId !== null) {
                $this->provisioner->ensureDatabase($tenantByLegacyId);

                return [
                    'tenant' => $tenantByLegacyId->refresh(),
                    'created' => false,
                    'reused_reason' => 'tenant_settings_legacy_customer_id',
                ];
            }

            if ($existingMigration?->tenant_id) {
                $tenant = Tenant::query()->find($existingMigration->tenant_id);
                if ($tenant !== null) {
                    return [
                        'tenant' => $tenant,
                        'created' => false,
                        'reused_reason' => 'legacy_migration_in_progress',
                    ];
                }
            }

            $plan = Plan::query()->where('is_active', true)->orderBy('sort_order')->first();
            if ($plan === null) {
                $plan = app(\App\Domains\LegacyMigration\Services\LegacyPlanImportService::class)->ensureDefaultPlan();
            }

            $slug = $this->slugService->generateUnique($customer->displayName());
            $phone = PhoneNormalizer::normalize($customer->phone);

            // Do NOT wrap in DB::transaction: TenantCreated runs CREATE DATABASE (DDL),
            // which MySQL implicitly commits and then Laravel fails with
            // "There is no active transaction".
            $tenant = Tenant::query()->create([
                'id' => $slug,
                'database_name' => $this->namingService->forTenantId($slug),
                'name' => $customer->displayName(),
                'company_name' => $customer->companyName ?: $customer->displayName(),
                'email' => $customer->email,
                'phone' => $phone,
                'status' => TenantStatus::Active,
                'plan_id' => $plan->id,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'country_code' => 'IN',
                'settings' => [
                    'legacy_customer_id' => $customer->id,
                    'legacy_customer_uid' => $customer->uid,
                ],
                'provisioned_at' => now(),
            ]);

            $tenant->domains()->create([
                'domain' => $slug,
                'is_primary' => true,
            ]);

            $this->provisioner->ensureDatabase($tenant);

            return [
                'tenant' => $tenant->refresh(),
                'created' => true,
                'reused_reason' => null,
            ];
        });
    }

    public function ensureOwnerAccess(Tenant $tenant, string $email, ?string $phone): void
    {
        tenancy()->central(function () use ($tenant, $email, $phone): void {
            $normalizedPhone = PhoneNormalizer::normalize($phone);

            $existing = TenantUserAccess::findActiveByEmail($email);

            if ($existing !== null) {
                if ($existing->tenant_id !== $tenant->id) {
                    throw new RuntimeException(
                        "Email [{$email}] is already linked to tenant [{$existing->tenant_id}]."
                    );
                }

                $existing->forceFill([
                    'phone' => $normalizedPhone ?? $existing->phone,
                    'account_type' => TenantUserAccountType::Owner,
                    'is_active' => true,
                ])->save();

                return;
            }

            TenantUserAccess::query()->create([
                'email' => strtolower($email),
                'phone' => $normalizedPhone,
                'tenant_id' => $tenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
            ]);
        });
    }

    private function attachLegacyMetadata(Tenant $tenant, LegacyCustomerSnapshot $customer): void
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['legacy_customer_id'] = $customer->id;
        $settings['legacy_customer_uid'] = $customer->uid;

        $tenant->forceFill([
            'settings' => $settings,
            'company_name' => $tenant->company_name ?: ($customer->companyName ?: $customer->displayName()),
            'phone' => PhoneNormalizer::normalize($customer->phone) ?: $tenant->phone,
        ])->save();
    }
}
