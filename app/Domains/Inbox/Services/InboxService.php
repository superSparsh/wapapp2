<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Inbox\Support\InboxPresenter;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxService
{
    public function __construct(
        private readonly InboxQueryService $queryService,
        private readonly InboxMessageService $messageService,
        private readonly InboxAssignmentService $assignmentService,
        private readonly InboxSettingsService $settingsService,
        private readonly InboxAccessService $accessService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request, ?Conversation $selected = null): array
    {
        $line = $this->resolveActiveLine($request, $selected);
        $filters = $this->filtersFromRequest($request);
        $filters['line'] = $line->uuid;

        $threads = $this->queryService->paginateThreads(
            line: $line,
            search: $filters['search'],
            unreadOnly: $filters['unread_only'],
            lookbackDays: $filters['lookback_days'],
            cursor: $filters['cursor'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
        );

        $messages = [];
        $messagesHasMore = false;
        $messagesOldestId = null;
        $selectedContact = null;

        if ($selected !== null) {
            $selected->load([
                'assignedUser:id,uuid,name,first_name',
                'assignedTeamMember:id,uuid,first_name,last_name,email',
            ]);

            $this->messageService->markRead($selected);
            $selected->refresh();

            // Threads were loaded before markRead — keep open chat badge cleared.
            $threads['items'] = array_map(static function (array $thread) use ($selected): array {
                if (($thread['uuid'] ?? null) === $selected->uuid) {
                    $thread['unread'] = 0;
                }

                return $thread;
            }, $threads['items']);

            $paginatedMessages = $this->messageService->paginateMessages(
                conversation: $selected,
                lookbackDays: $filters['lookback_days'],
            );

            $messages = $paginatedMessages['items'];
            $messagesHasMore = (bool) $paginatedMessages['has_more'];
            $messagesOldestId = $paginatedMessages['oldest_id'];

            $selectedContact = $this->contactCard($selected);
        }

        $walletBalance = $this->walletService->balance();
        $walletMin = (float) config('inbox.wallet_min_balance', 50);

        return [
            'threads' => $threads['items'],
            'threadsCursor' => $threads['next_cursor'],
            'threadsHasMore' => $threads['has_more'],
            'selectedConversation' => $selected,
            'selectedContact' => $selectedContact,
            'messages' => $messages,
            'messagesHasMore' => $messagesHasMore,
            'messagesOldestId' => $messagesOldestId,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'assignableAgents' => $this->assignmentService->assignableAgents(),
            'activeLine' => $line,
            'availableLines' => $this->availableLines(),
            'unreadTotal' => $this->queryService->totalUnreadCount(),
            'inboxPhoneMaskingEnabled' => $this->settingsService->isPhoneMaskingEnabled(),
            'isTeamInbox' => $this->accessService->isTeamMember(),
            'walletBalance' => $walletBalance,
            'walletBlocked' => $walletBalance <= $walletMin,
            'aiForAll' => (bool) session('inbox.ai_for_all', false),
        ];
    }

    public function authorizeConversation(Conversation $conversation): void
    {
        $this->accessService->assertCanAccessConversation($conversation);
    }

    public function deleteConversation(Conversation $conversation): void
    {
        $this->authorizeConversation($conversation);

        DB::transaction(function () use ($conversation): void {
            $conversation->messages()->delete();
            $conversation->forceDelete();
        });
    }

    public function requireDefaultLine(): WhatsappLine
    {
        $line = $this->queryService->resolveDefaultLine();

        abort_if($line === null, 404, 'No WhatsApp line configured for this account.');

        return $line;
    }

    /**
     * Resolve which WhatsApp line the inbox should use for this request.
     */
    public function resolveActiveLine(Request $request, ?Conversation $selected = null): WhatsappLine
    {
        $line = null;

        if ($selected !== null && $selected->whatsapp_line_id) {
            $candidate = WhatsappLine::query()->find((int) $selected->whatsapp_line_id);
            if ($candidate instanceof WhatsappLine && $this->lineIsAccessible($candidate)) {
                $line = $candidate;
            }
        }

        if ($line === null) {
            $requestLineUuid = $request->string('line')->trim()->toString() ?: null;
            if ($requestLineUuid !== null) {
                $line = $this->findAccessibleLineByUuid($requestLineUuid);
            }
        }

        if ($line === null) {
            $sessionUuid = $request->session()->get('inbox_selected_line_uuid');
            if (is_string($sessionUuid) && $sessionUuid !== '') {
                $line = $this->findAccessibleLineByUuid($sessionUuid);
            }
        }

        if ($line === null) {
            $line = $this->requireDefaultLine();
        }

        $request->session()->put('inbox_selected_line_uuid', $line->uuid);

        return $line;
    }

    /**
     * @return array<int, array{uuid: string, label: string, phone: string, is_default: bool}>
     */
    public function availableLines(): array
    {
        $query = WhatsappLine::query()
            ->orderByDesc('is_default')
            ->orderBy('id');

        $assignedLineIds = $this->accessService->assignedLineIds();
        if ($assignedLineIds !== []) {
            $query->whereIn('id', $assignedLineIds);
        }

        return $query->get()
            ->map(fn (WhatsappLine $line): array => [
                'uuid' => $line->uuid,
                'label' => $line->displayLabel(),
                'phone' => (string) $line->phone,
                'is_default' => (bool) $line->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     search: ?string,
     *     unread_only: bool,
     *     lookback_days: int,
     *     cursor: ?string,
     *     scope: ?string,
     *     assignee: ?string,
     *     assignee_filter: ?array,
     *     line: ?string
     * }
     */
    public function filtersFromRequest(Request $request): array
    {
        $lookback = $request->integer('days');
        $scope = $request->string('scope')->trim()->toString() ?: null;
        $assignee = $request->string('assignee')->trim()->toString() ?: null;
        $line = $request->string('line')->trim()->toString() ?: null;

        if (! in_array($scope, [null, '', 'all', 'unread', 'mine'], true)) {
            $scope = null;
        }

        if ($scope === 'all' || $scope === '') {
            $scope = null;
        }

        return [
            'search' => $request->string('q')->trim()->toString() ?: null,
            'unread_only' => $request->boolean('unread') || $scope === 'unread',
            'lookback_days' => $this->normalizeLookbackDays($lookback),
            'cursor' => $request->string('cursor')->trim()->toString() ?: null,
            'scope' => $scope,
            'assignee' => $assignee ?: null,
            'assignee_filter' => $this->assignmentService->resolveAssigneeFilter($assignee),
            'line' => $line,
        ];
    }

    /**
     * @return array{
     *     scopes: array<int, array{value: string, label: string}>,
     *     lookback_days: array<int, int>,
     *     assignees: array<int, array{value: string, label: string}>
     * }
     */
    public function filterOptions(): array
    {
        $assignees = collect([
            ['value' => 'all', 'label' => 'All Team Members'],
            ['value' => 'unassigned', 'label' => 'Unassigned'],
        ])->concat(
            $this->assignmentService->assignableAgents()->map(
                fn (array $agent): array => ['value' => $agent['key'], 'label' => $agent['label']],
            ),
        )->all();

        return [
            'scopes' => [
                ['value' => 'all', 'label' => 'All Conversations'],
                ['value' => 'unread', 'label' => 'Unread Only'],
                ['value' => 'mine', 'label' => 'Assigned to Me'],
            ],
            'lookback_days' => config('inbox.allowed_lookback_days', [1, 3, 7, 90, 180, 365]),
            'assignees' => $assignees,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactCard(Conversation $conversation): array
    {
        $assigneeKey = null;

        if ($conversation->assignedUser) {
            $assigneeKey = 'user:'.$conversation->assignedUser->uuid;
        } elseif ($conversation->assignedTeamMember) {
            $assigneeKey = 'member:'.$conversation->assignedTeamMember->uuid;
        }

        $conversation->loadMissing('contact');
        $stopped = $conversation->contact?->hasStoppedMessaging() ?? false;

        $phone = $this->settingsService->shouldMaskPhone($conversation->contact_phone);

        return [
            'uuid' => $conversation->uuid,
            'initials' => InboxPresenter::initials($conversation->contact_name, $conversation->contact_phone),
            'name' => InboxPresenter::threadTitle($conversation->contact_name, $phone),
            'phone' => $phone,
            'assignee' => $assigneeKey,
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
            'stopped' => $stopped,
            'stop_label' => $stopped ? 'Marked STOP — unsubscribed' : null,
        ];
    }

    private function normalizeLookbackDays(int $lookbackDays): int
    {
        $allowed = config('inbox.allowed_lookback_days', [1, 3, 7, 90, 180, 365]);

        if (! in_array($lookbackDays, $allowed, true)) {
            return (int) config('inbox.default_lookback_days', 7);
        }

        return $lookbackDays;
    }

    private function findAccessibleLineByUuid(string $uuid): ?WhatsappLine
    {
        $query = WhatsappLine::query()->where('uuid', $uuid);

        $assignedLineIds = $this->accessService->assignedLineIds();
        if ($assignedLineIds !== []) {
            $query->whereIn('id', $assignedLineIds);
        }

        return $query->first();
    }

    private function lineIsAccessible(WhatsappLine $line): bool
    {
        $assignedLineIds = $this->accessService->assignedLineIds();

        if ($assignedLineIds === []) {
            return true;
        }

        return in_array((int) $line->id, $assignedLineIds, true);
    }
}
