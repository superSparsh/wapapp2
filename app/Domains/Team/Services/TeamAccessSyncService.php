<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Enums\RecordStatus;
use App\Enums\TenantUserAccountType;
use App\Models\TeamMember;
use App\Models\TenantUserAccess;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class TeamAccessSyncService
{
    public function sync(TeamMember $member, bool $active = true): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        DB::connection(config('tenancy.database.central_connection', 'mysql'))
            ->table('tenant_user_access')
            ->updateOrInsert(
                ['email' => strtolower($member->email)],
                [
                    'tenant_id' => $tenantId,
                    'account_type' => TenantUserAccountType::Team->value,
                    'phone' => PhoneNormalizer::normalize($member->phone),
                    'is_active' => $active,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
    }

    public function deactivate(TeamMember $member): void
    {
        $this->sync($member, false);
    }

    public function remove(TeamMember $member): void
    {
        TenantUserAccess::query()
            ->where('email', strtolower($member->email))
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
}
