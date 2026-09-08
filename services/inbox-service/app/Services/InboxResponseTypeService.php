<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationResponseType;
use App\Models\Conversation;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class InboxResponseTypeService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function setForConversation(Conversation $conversation, ConversationResponseType $responseType): Conversation
    {
        $conversation->forceFill([
            'response_type' => $responseType->value,
        ])->save();

        return $conversation->refresh();
    }

    public function setForAllOnLine(
        int $lineId,
        ConversationResponseType $responseType,
        ?int $lookbackDays = null,
        ?string $scope = null,
        ?array $assigneeFilter = null,
    ): int {
        $lookbackDays = $lookbackDays ?? (int) config('inbox.default_lookback_days', 7);
        $since = now()->subDays($lookbackDays);

        $query = Conversation::query()
            ->where('whatsapp_line_id', $lineId)
            ->where('last_message_at', '>=', $since);

        if ($scope === 'unread') {
            $query->where('unread_count', '>', 0);
        }

        if ($scope === 'mine') {
            $userId = $this->tenantContext->getUserId();
            $teamMemberId = $this->tenantContext->getTeamMemberId();

            $query->where(function ($builder) use ($userId, $teamMemberId): void {
                if ($userId !== null && $teamMemberId !== null) {
                    $builder
                        ->where('assigned_user_id', $userId)
                        ->orWhere('assigned_team_member_id', $teamMemberId);

                    return;
                }

                if ($userId !== null) {
                    $builder->where('assigned_user_id', $userId);

                    return;
                }

                if ($teamMemberId !== null) {
                    $builder->where('assigned_team_member_id', $teamMemberId);

                    return;
                }

                $builder->whereRaw('1 = 0');
            });
        }

        if ($assigneeFilter !== null) {
            if (! empty($assigneeFilter['unassigned'])) {
                $query->whereNull('assigned_user_id')->whereNull('assigned_team_member_id');
            } elseif (isset($assigneeFilter['user_id'])) {
                $query->where('assigned_user_id', $assigneeFilter['user_id']);
            } elseif (isset($assigneeFilter['team_member_id'])) {
                $query->where('assigned_team_member_id', $assigneeFilter['team_member_id']);
            }
        }

        return DB::transaction(fn (): int => $query->update([
            'response_type' => $responseType->value,
        ]));
    }
}
