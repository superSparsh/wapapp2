<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Team\Support\TeamActor;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class TeamImpersonationService
{
    public function __construct(
        private readonly ManagerScopeService $scopeService,
    ) {}

    public function start(TeamMember $manager, TeamMember $member): void
    {
        abort_unless($manager->isManager(), 403);
        $this->scopeService->authorizeManage($manager, $member);
        $this->assertMemberCanBeImpersonated($member);

        if (! session()->has($this->sessionKey()) && ! session()->has($this->ownerSessionKey())) {
            session([$this->sessionKey() => $manager->id]);
        }

        Auth::guard('team')->login($member);
        $this->syncTeamPasswordHash($member);
    }

    public function startAsOwner(User $owner, TeamMember $member): void
    {
        abort_unless((int) $member->parent_user_id === (int) $owner->id, 403);
        $this->assertMemberCanBeImpersonated($member);

        session([
            $this->ownerSessionKey() => $owner->id,
        ]);
        session()->forget($this->sessionKey());

        Auth::guard('web')->logout();
        Auth::guard('team')->login($member);
        $this->syncTeamPasswordHash($member);
    }

    public function stop(): void
    {
        if ($this->isOwnerImpersonating()) {
            $this->stopOwner();

            return;
        }

        $managerId = session($this->sessionKey());

        if (! is_numeric($managerId)) {
            throw new AuthorizationException('No active impersonation session.');
        }

        $manager = TeamMember::query()->find((int) $managerId);

        session()->forget($this->sessionKey());
        session()->forget($this->ownerSessionKey());

        if ($manager === null || ! $manager->isManager()) {
            Auth::guard('team')->logout();

            return;
        }

        Auth::guard('team')->login($manager);
        $this->syncTeamPasswordHash($manager);
    }

    public function isImpersonating(): bool
    {
        return session()->has($this->sessionKey()) || session()->has($this->ownerSessionKey());
    }

    public function isOwnerImpersonating(): bool
    {
        return is_numeric(session($this->ownerSessionKey()));
    }

    public function isManagerImpersonating(): bool
    {
        return is_numeric(session($this->sessionKey()));
    }

    public function impersonator(): ?TeamMember
    {
        $managerId = session($this->sessionKey());

        if (! is_numeric($managerId)) {
            return null;
        }

        $manager = TeamMember::query()->find((int) $managerId);

        return $manager instanceof TeamMember ? $manager : null;
    }

    public function impersonatedMember(): ?TeamMember
    {
        if (! $this->isImpersonating()) {
            return null;
        }

        return TeamActor::teamMember();
    }

    public function stopRedirectRoute(): string
    {
        return $this->isOwnerImpersonating()
            ? 'my-team.index'
            : 'manager.team.index';
    }

    private function stopOwner(): void
    {
        $ownerId = session($this->ownerSessionKey());

        session()->forget($this->ownerSessionKey());
        session()->forget($this->sessionKey());

        Auth::guard('team')->logout();

        if (! is_numeric($ownerId)) {
            return;
        }

        $owner = User::query()->find((int) $ownerId);

        if ($owner === null) {
            return;
        }

        Auth::guard('web')->login($owner);
        $this->syncWebPasswordHash($owner);
    }

    private function assertMemberCanBeImpersonated(TeamMember $member): void
    {
        if (! $member->isActive()) {
            throw new AuthorizationException('Cannot login as an inactive team member.');
        }
    }

    private function syncTeamPasswordHash(TeamMember $member): void
    {
        $passwordHash = $member->getAuthPassword();
        if (is_string($passwordHash) && $passwordHash !== '') {
            session()->put(
                'password_hash_team',
                Auth::guard('team')->hashPasswordForCookie($passwordHash),
            );
        }
    }

    private function syncWebPasswordHash(User $owner): void
    {
        $passwordHash = $owner->getAuthPassword();
        if (is_string($passwordHash) && $passwordHash !== '') {
            session()->put(
                'password_hash_web',
                Auth::guard('web')->hashPasswordForCookie($passwordHash),
            );
        }
    }

    private function sessionKey(): string
    {
        return (string) config('team.impersonation_session_key', 'team_impersonator_id');
    }

    private function ownerSessionKey(): string
    {
        return (string) config('team.impersonation_owner_session_key', 'team_impersonator_owner_id');
    }
}
