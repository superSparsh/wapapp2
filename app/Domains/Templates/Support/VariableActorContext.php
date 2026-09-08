<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\Team\Support\TeamActor;
use App\Models\TeamMember;
use App\Models\WhatsappLine;

final class VariableActorContext
{
    public function teamMemberId(): ?int
    {
        return TeamActor::teamMember()?->id;
    }

    public function teamMemberName(): ?string
    {
        $member = TeamActor::teamMember();

        if (! $member instanceof TeamMember) {
            return null;
        }

        $name = trim($member->first_name.' '.$member->last_name);

        return $name !== '' ? $name : $member->email;
    }

    public function whatsappLineId(): ?int
    {
        return WhatsappLine::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }
}
