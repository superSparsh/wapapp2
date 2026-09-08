<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Support\InboxPresenter;
use App\Support\PhoneNormalizer;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InboxQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     next_cursor: ?string,
     *     has_more: bool
     * }
     */
    public function paginateThreads(
        ?int $lineId = null,
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

        $query = $this->baseThreadQuery($lineId, $since, $search, $unreadOnly, $scope, $assigneeFilter)
            ->with(['latestMessage' => fn ($query) => $query->select(
                'messages.id',
                'messages.conversation_id',
                'messages.body',
                'messages.created_at',
            )]);

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
            $maskPhone = $this->tenantContext->isPhoneMaskingEnabled();
            $phone = $maskPhone
                ? InboxPresenter::maskPhone($conversation->contact_phone)
                : $conversation->contact_phone;

            return [
                'uuid' => $conversation->uuid,
                'no' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'initials' => InboxPresenter::initials($conversation->contact_name, $conversation->contact_phone),
                'name' => $conversation->contact_name ?: $phone,
                'phone' => $phone,
                'time' => InboxPresenter::relativeTime($conversation->last_message_at),
                'preview' => $preview,
                'unread' => (int) $conversation->unread_count,
                'assigned_user_id' => $conversation->assigned_user_id,
                'assigned_team_member_id' => $conversation->assigned_team_member_id,
                'ai_enabled' => $conversation->response_type?->isAi() ?? false,
            ];
        })->all();

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    public function totalUnreadCount(?int $lineId = null): int
    {
        $query = Conversation::query()->where('unread_count', '>', 0);

        if ($lineId !== null && $lineId > 0) {
            $query->where('whatsapp_line_id', $lineId);
        }

        $this->applyTeamMemberScope($query);

        return (int) $query->sum('unread_count');
    }

    /**
     * @return array<int, int>
     */
    public function unreadConversationIds(
        ?int $lineId = null,
        ?int $lookbackDays = null,
        ?string $scope = null,
        ?array $assigneeFilter = null,
    ): array {
        $lookbackDays = $this->normalizeLookbackDays($lookbackDays);
        $since = now()->subDays($lookbackDays);

        return $this->baseThreadQuery($lineId, $since, null, false, $scope, $assigneeFilter)
            ->where('unread_count', '>', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
        $userId = $this->tenantContext->getUserId();
        $teamMemberId = $this->tenantContext->getTeamMemberId();

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
        ?int $lineId,
        Carbon $since,
        ?string $search,
        bool $unreadOnly,
        ?string $scope,
        ?array $assigneeFilter,
    ): Builder {
        return Conversation::query()
            ->when($lineId !== null && $lineId > 0, fn (Builder $builder) => $builder->where('whatsapp_line_id', $lineId))
            ->where('last_message_at', '>=', $since)
            ->when(true, fn (Builder $builder) => $this->applyTeamMemberScope($builder))
            ->when($unreadOnly || $scope === 'unread', fn (Builder $builder) => $builder->where('unread_count', '>', 0))
            ->when($scope === 'mine', fn (Builder $builder) => $this->applyMineScope($builder))
            ->when($assigneeFilter !== null, fn (Builder $builder) => $this->applyAssigneeFilter($builder, $assigneeFilter))
            ->when(filled($search), fn (Builder $builder) => $this->applySearch($builder, (string) $search));
    }

    private function applyTeamMemberScope(Builder $query): void
    {
        $teamMemberId = $this->tenantContext->getTeamMemberId();

        if ($teamMemberId === null) {
            return;
        }

        $query->where('assigned_team_member_id', $teamMemberId);

        $lineIds = $this->tenantContext->getAssignedLineIds();

        if ($lineIds !== []) {
            $query->whereIn('whatsapp_line_id', $lineIds);
        }
    }
}
