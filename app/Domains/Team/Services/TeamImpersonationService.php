<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Team\Support\TeamActor;
use App\Models\TeamMember;
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

        if (! session()->has($this->sessionKey())) {
            session([$this->sessionKey() => $manager->id]);
        }

        Auth::guard('team')->login($member);
    }

    public function stop(): void
    {
        $managerId = session($this->sessionKey());

        if (! is_numeric($managerId)) {
            throw new AuthorizationException('No active impersonation session.');
        }

        $manager = TeamMember::query()->find((int) $managerId);

        session()->forget($this->sessionKey());

        if ($manager === null || ! $manager->isManager()) {
            Auth::guard('team')->logout();

            return;
        }

        Auth::guard('team')->login($manager);
    }

    public function isImpersonating(): bool
    {
        return session()->has($this->sessionKey());
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

    private function sessionKey(): string
    {
        return (string) config('team.impersonation_session_key', 'team_impersonator_id');
    }
}
