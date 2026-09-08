<?php

namespace Tests\Concerns;

use App\Enums\RecordStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait InteractsWithTenants
{
    protected Tenant $testTenant;

    protected User $testUser;

    protected WhatsappLine $testLine;

    protected function setUpTenant(): void
    {
        $tenantId = 'test-'.Str::lower(Str::random(8));

        $this->testTenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Test Tenant',
            'status' => TenantStatus::Active,
        ]);

        $this->testTenant->domains()->create([
            'domain' => $tenantId.'.localhost',
            'is_primary' => true,
        ]);

        tenancy()->initialize($this->testTenant);

        $this->testUser = User::query()->create([
            'name' => 'Test Owner',
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => $tenantId.'@test.test',
            'phone' => '91'.substr(md5($tenantId), 0, 10),
            'password' => Hash::make('password'),
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);
        $this->testUser->forceFill(['email_verified_at' => now()])->save();

        $this->testLine = WhatsappLine::query()->create([
            'phone' => '919999999999',
            'display_name' => 'Test Line',
            'status' => RecordStatus::Active,
            'is_default' => true,
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->syncLine($this->testTenant->id, (int) $this->testLine->id, $this->testLine->phone);
    }

    protected function actingAsTenantUser(): static
    {
        return $this
            ->withSession([
                'auth' => [
                    'tenant_id' => $this->testTenant->id,
                    'guard' => 'web',
                ],
            ])
            ->actingAs($this->testUser, 'web');
    }

    protected function actingAsTeamMember(TeamMember $member): static
    {
        return $this
            ->withSession([
                'auth' => [
                    'tenant_id' => $this->testTenant->id,
                    'guard' => 'team',
                ],
            ])
            ->actingAs($member, 'team');
    }

    protected function actingAsManager(TeamMember $manager): static
    {
        return $this->actingAsTeamMember($manager);
    }

    protected function tearDownTenant(): void
    {
        $tenant = $this->testTenant ?? null;

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($tenant instanceof Tenant) {
            $tenant->delete();
        }
    }
}
