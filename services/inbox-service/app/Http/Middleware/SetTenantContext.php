<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-Id')
            ?: $request->input('tenant_id')
            ?: $request->route('tenant_id');

        if (! empty($tenantId) && is_string($tenantId)) {
            $this->tenantContext->setTenantId($tenantId);
        }

        $userId = $request->header('X-Actor-User-Id');
        $teamMemberId = $request->header('X-Actor-Team-Member-Id');
        $userUuid = $request->header('X-Actor-User-Uuid');
        $teamMemberUuid = $request->header('X-Actor-Team-Member-Uuid');
        $isTeamMember = filter_var($request->header('X-Actor-Is-Team-Member', 'false'), FILTER_VALIDATE_BOOLEAN);
        $phoneMasking = filter_var($request->header('X-Actor-Phone-Masking', 'false'), FILTER_VALIDATE_BOOLEAN);

        $assignedLinesHeader = $request->header('X-Actor-Assigned-Lines');
        $assignedLineIds = [];
        if (! empty($assignedLinesHeader)) {
            $assignedLineIds = array_filter(array_map('intval', explode(',', $assignedLinesHeader)));
        }

        $this->tenantContext->setActor(
            userId: $userId ? (int) $userId : null,
            teamMemberId: $teamMemberId ? (int) $teamMemberId : null,
            userUuid: $userUuid ? (string) $userUuid : null,
            teamMemberUuid: $teamMemberUuid ? (string) $teamMemberUuid : null,
            isTeamMember: $isTeamMember,
            assignedLineIds: $assignedLineIds,
            phoneMaskingEnabled: $phoneMasking,
        );

        return $next($request);
    }
}
