<?php

declare(strict_types=1);

namespace App\Support;

class TenantContext
{
    private ?string $tenantId = null;
    private ?int $userId = null;
    private ?int $teamMemberId = null;
    private ?string $userUuid = null;
    private ?string $teamMemberUuid = null;
    private ?string $userName = null;
    private ?string $teamMemberName = null;
    private bool $isTeamMember = false;

    public function setContext(
        ?string $tenantId,
        ?int $userId = null,
        ?int $teamMemberId = null,
        ?string $userUuid = null,
        ?string $teamMemberUuid = null,
        ?string $userName = null,
        ?string $teamMemberName = null,
        bool $isTeamMember = false,
    ): self {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->teamMemberId = $teamMemberId;
        $this->userUuid = $userUuid;
        $this->teamMemberUuid = $teamMemberUuid;
        $this->userName = $userName;
        $this->teamMemberName = $teamMemberName;
        $this->isTeamMember = $isTeamMember;

        return $this;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getTeamMemberId(): ?int
    {
        return $this->teamMemberId;
    }

    public function getUserUuid(): ?string
    {
        return $this->userUuid;
    }

    public function getTeamMemberUuid(): ?string
    {
        return $this->teamMemberUuid;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getTeamMemberName(): ?string
    {
        return $this->teamMemberName;
    }

    public function isTeamMember(): bool
    {
        return $this->isTeamMember;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null && $this->tenantId !== '';
    }

    public function reset(): void
    {
        $this->tenantId = null;
        $this->userId = null;
        $this->teamMemberId = null;
        $this->userUuid = null;
        $this->teamMemberUuid = null;
        $this->userName = null;
        $this->teamMemberName = null;
        $this->isTeamMember = false;
    }
}
