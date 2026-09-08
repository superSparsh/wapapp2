<?php

declare(strict_types=1);

namespace App\Support;

class VariableActorContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function whatsappLineId(): ?int
    {
        return $this->tenantContext->getWhatsappLineId();
    }

    public function teamMemberId(): ?int
    {
        return $this->tenantContext->getTeamMemberId();
    }

    public function teamMemberName(): ?string
    {
        return $this->tenantContext->getTeamMemberName();
    }

    public function userId(): ?int
    {
        return $this->tenantContext->getUserId();
    }

    public function userName(): ?string
    {
        return $this->tenantContext->getUserName();
    }
}
