<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxPresenter;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use Illuminate\Http\Request;

class InboxService
{
    public function __construct(
        private readonly InboxQueryService $queryService,
        private readonly InboxMessageService $messageService,
        private readonly InboxAssignmentService $assignmentService,
        private readonly InboxSettingsService $settingsService,
        private readonly InboxAccessService $accessService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request, ?Conversation $selected = null): array
    {
        $line = $this->requireDefaultLine();
        $filters = $this->filtersFromRequest($request);

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
        $selectedContact = null;

        if ($selected !== null) {
            $selected->load([
                'assignedUser:id,uuid,name,first_name',
                'assignedTeamMember:id,uuid,first_name,last_name,email',
            ]);

            $this->messageService->markRead($selected);
            $selected->refresh();

            $messages = $this->messageService->paginateMessages(
                conversation: $selected,
                lookbackDays: $filters['lookback_days'],
            )['items'];

            $selectedContact = $this->contactCard($selected);
        }

        return [
            'threads' => $threads['items'],
            'threadsCursor' => $threads['next_cursor'],
            'threadsHasMore' => $threads['has_more'],
            'selectedConversation' => $selected,
            'selectedContact' => $selectedContact,
            'messages' => $messages,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'assignableAgents' => $this->assignmentService->assignableAgents(),
            'activeLine' => $line,
            'unreadTotal' => $this->queryService->totalUnreadCount($line),
            'inboxPhoneMaskingEnabled' => $this->settingsService->isPhoneMaskingEnabled(),
            'isTeamInbox' => $this->accessService->isTeamMember(),
        ];
    }

    public function authorizeConversation(Conversation $conversation): void
    {
        $this->accessService->assertCanAccessConversation($conversation);
    }

    public function requireDefaultLine(): WhatsappLine
    {
        $line = $this->queryService->resolveDefaultLine();

        abort_if($line === null, 404, 'No WhatsApp line configured for this account.');

        return $line;
    }

    /**
     * @return array{
     *     search: ?string,
     *     unread_only: bool,
     *     lookback_days: int,
     *     cursor: ?string,
     *     scope: ?string,
     *     assignee: ?string,
     *     assignee_filter: ?array
     * }
     */
    public function filtersFromRequest(Request $request): array
    {
        $lookback = $request->integer('days');
        $scope = $request->string('scope')->trim()->toString() ?: null;
        $assignee = $request->string('assignee')->trim()->toString() ?: null;

        if (! in_array($scope, [null, '', 'all', 'unread', 'mine'], true)) {
            $scope = null;
        }

        if ($scope === 'all' || $scope === '') {
            $scope = null;
        }

        return [
            'search' => $request->string('q')->trim()->toString() ?: null,
            'unread_only' => $request->boolean('unread') || $scope === 'unread',
            'lookback_days' => $lookback > 0 ? $lookback : (int) config('inbox.default_lookback_days', 7),
            'cursor' => $request->string('cursor')->trim()->toString() ?: null,
            'scope' => $scope,
            'assignee' => $assignee ?: null,
            'assignee_filter' => $this->assignmentService->resolveAssigneeFilter($assignee),
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
            'lookback_days' => config('inbox.allowed_lookback_days', [1, 3, 7, 30, 90]),
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

        return [
            'uuid' => $conversation->uuid,
            'initials' => InboxPresenter::initials($conversation->contact_name, $conversation->contact_phone),
            'name' => $conversation->contact_name ?: 'Unknown',
            'phone' => $this->settingsService->shouldMaskPhone($conversation->contact_phone),
            'assignee' => $assigneeKey,
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
        ];
    }
}
