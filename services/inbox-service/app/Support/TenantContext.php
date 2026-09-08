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
    /** @var array<int, int> */
    private array $assignedLineIds = [];
    private bool $phoneMaskingEnabled = false;

    public function setTenantId(?string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function requireTenantId(): string
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            abort(400, 'Tenant context is required for this operation.');
        }

        return $this->tenantId;
    }

    public function setActor(
        ?int $userId = null,
        ?int $teamMemberId = null,
        ?string $userUuid = null,
        ?string $teamMemberUuid = null,
        ?string $userName = null,
        ?string $teamMemberName = null,
        bool $isTeamMember = false,
        array $assignedLineIds = [],
        bool $phoneMaskingEnabled = false,
    ): self {
        $this->userId = $userId;
        $this->teamMemberId = $teamMemberId;
        $this->userUuid = $userUuid;
        $this->teamMemberUuid = $teamMemberUuid;
        $this->userName = $userName;
        $this->teamMemberName = $teamMemberName;
        $this->isTeamMember = $isTeamMember;
        $this->assignedLineIds = $assignedLineIds;
        $this->phoneMaskingEnabled = $phoneMaskingEnabled;

        return $this;
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

    public function isTeamMember(): bool
    {
        return $this->isTeamMember;
    }

    /** @return array<int, int> */
    public function getAssignedLineIds(): array
    {
        return $this->assignedLineIds;
    }

    public function isPhoneMaskingEnabled(): bool
    {
        return $this->phoneMaskingEnabled;
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
        $this->assignedLineIds = [];
        $this->phoneMaskingEnabled = false;
    }
}
