<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\Team\Support\TeamActor;
use App\Enums\RecordStatus;
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
        $member = TeamActor::teamMember();
        $assignedIds = [];

        if ($member instanceof TeamMember) {
            $assignedIds = collect($member->assigned_whatsapp_line_ids ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        $query = WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->orderByDesc('is_default')
            ->orderBy('id');

        if ($assignedIds !== []) {
            $query->whereIn('id', $assignedIds);
        }

        return $query->value('id');
    }
}
