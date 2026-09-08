<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantHeader = (string) config('service-auth.tenant_header', 'X-Tenant-Id');
        $tenantId = $request->header($tenantHeader)
            ?? $request->input('tenant_id')
            ?? $request->query('tenant_id');

        if ($tenantId === null || $tenantId === '') {
            return new JsonResponse([
                'error' => 'Missing required X-Tenant-Id header.',
                'code' => 'TENANT_HEADER_REQUIRED',
            ], 400);
        }

        $userIdHeader = (string) config('service-auth.actor_user_id_header', 'X-Actor-User-Id');
        $teamMemberIdHeader = (string) config('service-auth.actor_team_member_id_header', 'X-Actor-Team-Member-Id');
        $userUuidHeader = (string) config('service-auth.actor_user_uuid_header', 'X-Actor-User-Uuid');
        $teamMemberUuidHeader = (string) config('service-auth.actor_team_member_uuid_header', 'X-Actor-Team-Member-Uuid');
        $userNameHeader = (string) config('service-auth.actor_user_name_header', 'X-Actor-User-Name');
        $teamMemberNameHeader = (string) config('service-auth.actor_team_member_name_header', 'X-Actor-Team-Member-Name');
        $isTeamMemberHeader = (string) config('service-auth.actor_is_team_member_header', 'X-Actor-Is-Team-Member');

        $userId = $request->header($userIdHeader) ? (int) $request->header($userIdHeader) : null;
        $teamMemberId = $request->header($teamMemberIdHeader) ? (int) $request->header($teamMemberIdHeader) : null;
        $userUuid = $request->header($userUuidHeader);
        $teamMemberUuid = $request->header($teamMemberUuidHeader);
        $userName = $request->header($userNameHeader);
        $teamMemberName = $request->header($teamMemberNameHeader);
        $isTeamMember = $request->header($isTeamMemberHeader) === '1' || $request->header($isTeamMemberHeader) === 'true';

        $this->tenantContext->setContext(
            tenantId: (string) $tenantId,
            userId: $userId,
            teamMemberId: $teamMemberId,
            userUuid: $userUuid ? (string) $userUuid : null,
            teamMemberUuid: $teamMemberUuid ? (string) $teamMemberUuid : null,
            userName: $userName ? (string) $userName : null,
            teamMemberName: $teamMemberName ? (string) $teamMemberName : null,
            isTeamMember: $isTeamMember,
        );

        return $next($request);
    }
}
