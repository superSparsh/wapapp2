<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxActor;
use App\Domains\Integration\Services\PhoneLineService;
use App\Models\Conversation;
use App\Models\TeamMember;

class InboxAccessService
{
    public function canAccessConversation(Conversation $conversation): bool
    {
        $lockedLineId = PhoneLineService::lockedLineId();
        if ($lockedLineId !== null && (int) $conversation->whatsapp_line_id !== $lockedLineId) {
            return false;
        }

        $member = InboxActor::teamMember();

        if ($member === null) {
            return true;
        }

        if ((int) $conversation->assigned_team_member_id !== (int) $member->id) {
            return false;
        }

        return $this->lineIsAssigned($member, (int) $conversation->whatsapp_line_id);
    }

    public function assertCanAccessConversation(Conversation $conversation): void
    {
        abort_unless(
            $this->canAccessConversation($conversation),
            403,
            'This chat is not assigned to your account.',
        );
    }

    public function isTeamMember(): bool
    {
        return InboxActor::teamMember() instanceof TeamMember;
    }

    /** @return array<int, int> */
    public function assignedLineIds(): array
    {
        $lockedLineId = PhoneLineService::lockedLineId();
        if ($lockedLineId !== null) {
            return [$lockedLineId];
        }

        $member = InboxActor::teamMember();

        if ($member === null) {
            return [];
        }

        return collect($member->assigned_whatsapp_line_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    public function lineIsAssigned(TeamMember $member, int $lineId): bool
    {
        $lineIds = collect($member->assigned_whatsapp_line_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        // No line restriction configured → member can use any account line.
        if ($lineIds === []) {
            return true;
        }

        return in_array($lineId, $lineIds, true);
    }
}
