<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxActor;
use App\Domains\Inbox\Support\InboxPresenter;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;

class InboxQueryService
{
    public function __construct(
        private readonly InboxSettingsService $settingsService,
        private readonly InboxAccessService $accessService,
    ) {}
    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     next_cursor: ?string,
     *     has_more: bool
     * }
     */
    public function paginateThreads(
        WhatsappLine $line,
        ?string $search = null,
        bool $unreadOnly = false,
        ?int $lookbackDays = null,
        ?string $cursor = null,
        ?int $limit = null,
        ?string $scope = null,
        ?array $assigneeFilter = null,
    ): array {
        $limit = max(1, min(50, $limit ?? (int) config('inbox.threads_per_page', 25)));
        $lookbackDays = $this->normalizeLookbackDays($lookbackDays);
        $since = now()->subDays($lookbackDays);

        $query = $this->baseThreadQuery($line, $since, $search, $unreadOnly, $scope, $assigneeFilter)
            ->with(['latestMessage' => fn ($query) => $query->select(
                'messages.id',
                'messages.conversation_id',
                'messages.body',
                'messages.created_at',
            )])
            ->with([
                'assignedUser:id,uuid,name,first_name',
                'assignedTeamMember:id,uuid,first_name,last_name,email',
            ]);

        $query
            ->when(filled($cursor), fn (Builder $builder) => $this->applyCursor($builder, (string) $cursor))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $last = $rows->last();
        $nextCursor = $hasMore && $last !== null
            ? InboxPresenter::encodeCursor($last->last_message_at, (int) $last->id)
            : null;

        $items = $rows->values()->map(function (Conversation $conversation, int $index): array {
            $preview = InboxPresenter::preview($conversation->latestMessage?->body);

            return [
                'uuid' => $conversation->uuid,
                'no' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'initials' => InboxPresenter::initials($conversation->contact_name, $conversation->contact_phone),
                'name' => $conversation->contact_name ?: $this->settingsService->shouldMaskPhone($conversation->contact_phone),
                'phone' => $this->settingsService->shouldMaskPhone($conversation->contact_phone),
                'time' => InboxPresenter::relativeTime($conversation->last_message_at),
                'preview' => $preview,
                'unread' => (int) $conversation->unread_count,
                'assignee' => $this->assigneeLabel($conversation),
                'ai_enabled' => $conversation->response_type?->isAi() ?? false,
            ];
        })->all();

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    public function totalUnreadCount(?WhatsappLine $line = null): int
    {
        $query = Conversation::query()->where('unread_count', '>', 0);

        if ($line !== null) {
            $query->where('whatsapp_line_id', $line->id);
        }

        $this->applyTeamMemberScope($query);

        return (int) $query->sum('unread_count');
    }

    /**
     * @return array<int, int>
     */
    public function unreadConversationIds(
        WhatsappLine $line,
        ?int $lookbackDays = null,
        ?string $scope = null,
        ?array $assigneeFilter = null,
    ): array {
        $lookbackDays = $this->normalizeLookbackDays($lookbackDays);
        $since = now()->subDays($lookbackDays);

        return $this->baseThreadQuery($line, $since, null, false, $scope, $assigneeFilter)
            ->where('unread_count', '>', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function resolveDefaultLine(): ?WhatsappLine
    {
        $assignedLineIds = $this->accessService->assignedLineIds();

        if ($assignedLineIds !== []) {
            return WhatsappLine::query()
                ->whereIn('id', $assignedLineIds)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
        }

        return WhatsappLine::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        $query->where(function (Builder $builder) use ($search): void {
            $builder->where('contact_name', 'like', '%'.$search.'%');

            foreach (PhoneNormalizer::lookupVariants($search) as $variant) {
                $builder->orWhere('contact_phone', 'like', '%'.$variant.'%');
            }

            $builder->orWhere('contact_phone', 'like', '%'.$search.'%');
        });
    }

    private function applyCursor(Builder $query, string $cursor): void
    {
        [$cursorAt, $cursorId] = InboxPresenter::decodeCursor($cursor);

        if ($cursorAt === null || $cursorId === null) {
            return;
        }

        $query->where(function (Builder $builder) use ($cursorAt, $cursorId): void {
            $builder
                ->where('last_message_at', '<', $cursorAt)
                ->orWhere(function (Builder $nested) use ($cursorAt, $cursorId): void {
                    $nested
                        ->where('last_message_at', '=', $cursorAt)
                        ->where('id', '<', $cursorId);
                });
        });
    }

    private function applyMineScope(Builder $query): void
    {
        $userId = InboxActor::userId();
        $teamMemberId = InboxActor::teamMemberId();

        $query->where(function (Builder $builder) use ($userId, $teamMemberId): void {
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

    /**
     * @param  array{unassigned?: bool, user_id?: int, team_member_id?: int}  $assigneeFilter
     */
    private function applyAssigneeFilter(Builder $query, array $assigneeFilter): void
    {
        if (! empty($assigneeFilter['unassigned'])) {
            $query->whereNull('assigned_user_id')->whereNull('assigned_team_member_id');

            return;
        }

        if (isset($assigneeFilter['user_id'])) {
            $query->where('assigned_user_id', $assigneeFilter['user_id']);

            return;
        }

        if (isset($assigneeFilter['team_member_id'])) {
            $query->where('assigned_team_member_id', $assigneeFilter['team_member_id']);
        }
    }

    private function assigneeLabel(Conversation $conversation): ?string
    {
        if ($conversation->assignedTeamMember) {
            $member = $conversation->assignedTeamMember;

            return trim($member->first_name.' '.$member->last_name) ?: $member->email;
        }

        if ($conversation->assignedUser) {
            $user = $conversation->assignedUser;

            return trim((string) ($user->first_name ?: $user->name)) ?: null;
        }

        return null;
    }

    private function normalizeLookbackDays(?int $lookbackDays): int
    {
        $lookbackDays ??= (int) config('inbox.default_lookback_days', 7);
        $allowed = config('inbox.allowed_lookback_days', [1, 3, 7, 30, 90]);

        if (! in_array($lookbackDays, $allowed, true)) {
            return (int) config('inbox.default_lookback_days', 7);
        }

        return $lookbackDays;
    }

    private function baseThreadQuery(
        WhatsappLine $line,
        \Illuminate\Support\Carbon $since,
        ?string $search,
        bool $unreadOnly,
        ?string $scope,
        ?array $assigneeFilter,
    ): Builder {
        return Conversation::query()
            ->where('whatsapp_line_id', $line->id)
            ->where('last_message_at', '>=', $since)
            ->when(true, fn (Builder $builder) => $this->applyTeamMemberScope($builder))
            ->when($unreadOnly || $scope === 'unread', fn (Builder $builder) => $builder->where('unread_count', '>', 0))
            ->when($scope === 'mine', fn (Builder $builder) => $this->applyMineScope($builder))
            ->when($assigneeFilter !== null, fn (Builder $builder) => $this->applyAssigneeFilter($builder, $assigneeFilter))
            ->when(filled($search), fn (Builder $builder) => $this->applySearch($builder, (string) $search));
    }

    private function applyTeamMemberScope(Builder $query): void
    {
        $teamMemberId = InboxActor::teamMemberId();

        if ($teamMemberId === null) {
            return;
        }

        $query->where('assigned_team_member_id', $teamMemberId);

        $lineIds = $this->accessService->assignedLineIds();

        if ($lineIds !== []) {
            $query->whereIn('whatsapp_line_id', $lineIds);
        }
    }
}
