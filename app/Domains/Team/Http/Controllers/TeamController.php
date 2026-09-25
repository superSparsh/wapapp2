<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Http\Requests\StoreTeamMemberRequest;
use App\Domains\Team\Http\Requests\UpdateTeamMemberRequest;
use App\Domains\Team\Http\Requests\UpdateTeamPermissionsRequest;
use App\Domains\Team\Services\TeamAccessService;
use App\Domains\Team\Services\TeamImpersonationService;
use App\Domains\Team\Services\TeamMemberService;
use App\Domains\Team\Services\TeamQueryService;
use App\Domains\Team\Services\TeamRedirectService;
use App\Domains\Team\Support\TeamPermissions;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\WhatsappLine;
use App\Support\ListingSort;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(
        Request $request,
        TeamAccessService $accessService,
        TeamQueryService $queryService,
    ): View {
        $owner = $accessService->owner();
        $usage = $accessService->usageForOwner($owner);
        $parsed = ListingSort::fromRequest(
            $request,
            ['created_at', 'name', 'phone', 'email', 'status'],
            'created_at',
            'desc',
        );

        return view('my-team.index', [
            'members' => $queryService->paginateForOwner(
                owner: $owner,
                search: $request->string('q')->trim()->toString() ?: null,
                perPage: (int) config('team.per_page', 10),
                sort: $parsed['sort'],
                direction: $parsed['direction'],
            ),
            'search' => $request->string('q')->trim()->toString(),
            'canCreate' => ! $usage['is_full'],
            'usage' => $usage,
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
        ]);
    }

    public function create(TeamAccessService $accessService): View
    {
        $accessService->assertCanCreate();

        return view('my-team.create', [
            'roles' => TeamMemberRole::cases(),
            'whatsappLines' => $this->whatsappLines(),
        ]);
    }

    public function store(
        StoreTeamMemberRequest $request,
        TeamAccessService $accessService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $accessService->assertCanCreate();

        $member = $memberService->create(
            $accessService->owner(),
            $request->validated(),
        );

        return redirect()
            ->route('my-team.roles', $member)
            ->with('status', 'Team member created. Configure permissions below.');
    }

    public function edit(TeamMember $teamMember, TeamAccessService $accessService): View
    {
        $accessService->authorizeMember($teamMember);

        return view('my-team.edit', [
            'member' => $teamMember,
            'whatsappLines' => $this->whatsappLines(),
            'assignedLineIds' => $teamMember->assigned_whatsapp_line_ids ?? [],
        ]);
    }

    public function update(
        UpdateTeamMemberRequest $request,
        TeamMember $teamMember,
        TeamAccessService $accessService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $accessService->authorizeMember($teamMember);
        $memberService->update($teamMember, $request->validated());

        return redirect()
            ->route('my-team.index')
            ->with('status', 'Team member updated.');
    }

    public function destroy(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $accessService->authorizeMember($teamMember);
        $memberService->delete($teamMember);

        return redirect()
            ->route('my-team.index')
            ->with('status', 'Team member deleted.');
    }

    public function toggleStatus(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        TeamMemberService $memberService,
    ): JsonResponse {
        $accessService->authorizeMember($teamMember);

        $member = $memberService->toggleStatus($teamMember);

        return response()->json([
            'ok' => true,
            'status' => $member->status->value,
            'is_active' => $member->isActive(),
        ]);
    }

    public function roles(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        TeamQueryService $queryService,
    ): View {
        $accessService->authorizeMember($teamMember);
        $owner = $accessService->owner();

        return view('my-team.roles', [
            'member' => $teamMember,
            'permissions' => TeamPermissions::normalize($teamMember->permissions),
            'permissionLabels' => TeamPermissions::labels(),
            'assignableMembers' => $teamMember->isManager()
                ? $queryService->assignableMembers($owner, $teamMember)
                : [],
            'assignedMemberUuids' => $teamMember->isManager()
                ? $queryService->assignedMemberUuids($teamMember)
                : [],
        ]);
    }

    public function updateRoles(
        UpdateTeamPermissionsRequest $request,
        TeamMember $teamMember,
        TeamAccessService $accessService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $accessService->authorizeMember($teamMember);
        $owner = $accessService->owner();

        $memberService->updatePermissions($teamMember, $request->permissionsInput());

        if ($teamMember->isManager()) {
            $memberService->syncManagerAssignments($owner, $teamMember, $request->memberUuids());
        }

        return redirect()
            ->route('my-team.index')
            ->with('status', 'Roles and permissions saved.');
    }

    public function loginAs(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        TeamImpersonationService $impersonationService,
        TeamRedirectService $redirectService,
    ): RedirectResponse {
        $accessService->authorizeMember($teamMember);

        try {
            $url = $redirectService->landingUrl($teamMember);
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('my-team.index')
                ->with('status', $teamMember->displayName().' has no module access configured. Assign permissions before using Login as.');
        }

        $impersonationService->startAsOwner($accessService->owner(), $teamMember);

        return redirect($url)
            ->with('status', 'You are now impersonating '.$teamMember->displayName().'.');
    }

    /** @return \Illuminate\Support\Collection<int, WhatsappLine> */
    private function whatsappLines()
    {
        return WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->orderByDesc('is_default')
            ->orderBy('phone')
            ->get(['id', 'uuid', 'phone', 'display_name']);
    }
}
