<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

class InboxAssignmentService
{
    public function assign(Conversation $conversation, ?int $userId, ?int $teamMemberId): Conversation
    {
        return DB::transaction(function () use ($conversation, $userId, $teamMemberId): Conversation {
            $conversation->forceFill([
                'assigned_user_id' => $userId,
                'assigned_team_member_id' => $teamMemberId,
            ])->save();

            return $conversation->refresh();
        });
    }

    public function assignByKey(
        Conversation $conversation,
        ?string $assigneeKey,
        ?int $resolvedUserId = null,
        ?int $resolvedTeamMemberId = null,
    ): Conversation {
        if ($assigneeKey === null || $assigneeKey === '' || $assigneeKey === 'unassigned') {
            return $this->assign($conversation, null, null);
        }

        return $this->assign($conversation, $resolvedUserId, $resolvedTeamMemberId);
    }
}
