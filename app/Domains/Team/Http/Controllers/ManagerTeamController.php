<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Http\Requests\StoreTeamMemberRequest;
use App\Domains\Team\Http\Requests\UpdateTeamMemberRequest;
use App\Domains\Team\Http\Requests\UpdateTeamPermissionsRequest;
use App\Domains\Team\Services\ManagerScopeService;
use App\Domains\Team\Services\TeamAccessService;
use App\Domains\Team\Services\TeamImpersonationService;
use App\Domains\Team\Services\TeamMemberService;
use App\Domains\Team\Services\TeamQueryService;
use App\Domains\Team\Services\TeamRedirectService;
use App\Domains\Team\Support\TeamPermissions;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WhatsappLine;
use App\Enums\RecordStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerTeamController extends Controller
{
    public function index(
        Request $request,
        TeamAccessService $accessService,
        TeamQueryService $queryService,
    ): View {
        $manager = $accessService->manager();
        $owner = User::query()->findOrFail($manager->parent_user_id);
        $usage = $accessService->usageForOwner($owner);

        return view('manager.team.index', [
            'members' => $queryService->paginateForManager(
                manager: $manager,
                search: $request->string('q')->trim()->toString() ?: null,
                perPage: (int) config('team.per_page', 10),
            ),
            'search' => $request->string('q')->trim()->toString(),
            'canCreate' => ! $usage['is_full'],
            'usage' => $usage,
        ]);
    }

    public function create(TeamAccessService $accessService): View
    {
        $accessService->manager();
        $accessService->assertCanCreate();

        return view('manager.team.create', [
            'whatsappLines' => $this->whatsappLines(),
        ]);
    }

    public function store(
        StoreTeamMemberRequest $request,
        TeamAccessService $accessService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $manager = $accessService->manager();
        $accessService->assertCanCreate();

        $member = $memberService->createForManager($manager, $request->validated());

        return redirect()
            ->route('manager.team.roles', $member)
            ->with('status', 'Team member created. Configure permissions below.');
    }

    public function edit(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        ManagerScopeService $scopeService,
    ): View {
        $manager = $accessService->manager();
        $scopeService->authorizeManage($manager, $teamMember);

        return view('manager.team.edit', [
            'member' => $teamMember,
            'whatsappLines' => $this->whatsappLines(),
            'assignedLineIds' => $teamMember->assigned_whatsapp_line_ids ?? [],
        ]);
    }

    public function update(
        UpdateTeamMemberRequest $request,
        TeamMember $teamMember,
        TeamAccessService $accessService,
        ManagerScopeService $scopeService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $manager = $accessService->manager();
        $scopeService->authorizeManage($manager, $teamMember);
        $memberService->update($teamMember, $request->validated());

        return redirect()
            ->route('manager.team.index')
            ->with('status', 'Team member updated.');
    }

    public function destroy(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        ManagerScopeService $scopeService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $manager = $accessService->manager();
        $scopeService->authorizeManage($manager, $teamMember);
        $memberService->delete($teamMember);

        return redirect()
            ->route('manager.team.index')
            ->with('status', 'Team member deleted.');
    }

    public function toggleStatus(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        ManagerScopeService $scopeService,
        TeamMemberService $memberService,
    ): JsonResponse {
        $manager = $accessService->manager();
        $scopeService->authorizeManage($manager, $teamMember);

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
        ManagerScopeService $scopeService,
    ): View {
        $accessService->manager();
        $scopeService->authorizeManage($accessService->manager(), $teamMember);

        return view('manager.team.roles', [
            'member' => $teamMember,
            'permissions' => TeamPermissions::normalize($teamMember->permissions),
            'permissionLabels' => TeamPermissions::labels(),
        ]);
    }

    public function updateRoles(
        UpdateTeamPermissionsRequest $request,
        TeamMember $teamMember,
        TeamAccessService $accessService,
        ManagerScopeService $scopeService,
        TeamMemberService $memberService,
    ): RedirectResponse {
        $manager = $accessService->manager();
        $scopeService->authorizeManage($manager, $teamMember);
        $memberService->updatePermissions($teamMember, $request->permissionsInput());

        return redirect()
            ->route('manager.team.index')
            ->with('status', 'Roles and permissions saved.');
    }

    public function loginAs(
        TeamMember $teamMember,
        TeamAccessService $accessService,
        ManagerScopeService $scopeService,
        TeamImpersonationService $impersonationService,
        TeamRedirectService $redirectService,
    ): RedirectResponse {
        $manager = $accessService->manager();
        $scopeService->authorizeManage($manager, $teamMember);
        $impersonationService->start($manager, $teamMember);

        return redirect($redirectService->landingUrl($teamMember))
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
