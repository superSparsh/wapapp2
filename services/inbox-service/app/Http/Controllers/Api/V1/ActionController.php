<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationResponseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignInboxConversationRequest;
use App\Http\Requests\ToggleInboxResponseTypeRequest;
use App\Models\Conversation;
use App\Services\InboxAssignmentService;
use App\Services\InboxMessageService;
use App\Services\InboxQueryService;
use App\Services\InboxResponseTypeService;
use App\Services\MessagingWindowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function __construct(
        private readonly InboxMessageService $messageService,
        private readonly InboxAssignmentService $assignmentService,
        private readonly InboxResponseTypeService $responseTypeService,
        private readonly MessagingWindowService $windowService,
        private readonly InboxQueryService $queryService,
    ) {}

    public function markRead(string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();
        $this->messageService->markRead($conversation);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $lineId = $request->integer('line_id') ?: null;
        $lookbackDays = $request->integer('lookback_days') ?: $request->integer('days') ?: null;
        $scope = $request->string('scope')->trim()->toString() ?: null;

        $assigneeFilter = null;
        if ($request->string('assignee')->toString() === 'unassigned') {
            $assigneeFilter = ['unassigned' => true];
        } elseif ($request->has('assigned_user_id')) {
            $assigneeFilter = ['user_id' => $request->integer('assigned_user_id')];
        } elseif ($request->has('assigned_team_member_id')) {
            $assigneeFilter = ['team_member_id' => $request->integer('assigned_team_member_id')];
        }

        $ids = $this->queryService->unreadConversationIds(
            lineId: $lineId,
            lookbackDays: $lookbackDays,
            scope: $scope,
            assigneeFilter: $assigneeFilter,
        );

        $updated = $this->messageService->markAllReadByIds($ids);

        return response()->json([
            'ok' => true,
            'updated' => $updated,
        ]);
    }

    public function assign(AssignInboxConversationRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        $userId = $request->has('user_id') ? $request->integer('user_id') : null;
        $teamMemberId = $request->has('team_member_id') ? $request->integer('team_member_id') : null;

        $conversation = $this->assignmentService->assign(
            $conversation,
            $userId,
            $teamMemberId,
        );

        return response()->json([
            'ok' => true,
            'assigned_user_id' => $conversation->assigned_user_id,
            'assigned_team_member_id' => $conversation->assigned_team_member_id,
        ]);
    }

    public function toggleResponseType(ToggleInboxResponseTypeRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        $responseType = $request->boolean('ai_enabled')
            ? ConversationResponseType::Ai
            : ConversationResponseType::Human;

        $conversation = $this->responseTypeService->setForConversation($conversation, $responseType);

        return response()->json([
            'ok' => true,
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
        ]);
    }

    public function toggleAllResponseType(ToggleInboxResponseTypeRequest $request): JsonResponse
    {
        $lineId = $request->integer('line_id');
        abort_if($lineId <= 0, 422, 'Valid line_id is required.');

        $responseType = $request->boolean('ai_enabled')
            ? ConversationResponseType::Ai
            : ConversationResponseType::Human;

        $updated = $this->responseTypeService->setForAllOnLine(
            lineId: $lineId,
            responseType: $responseType,
            lookbackDays: $request->integer('lookback_days') ?: null,
            scope: $request->string('scope')->trim()->toString() ?: null,
            assigneeFilter: $request->validated('assignee_filter'),
        );

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'ai_enabled' => $responseType->isAi(),
        ]);
    }

    public function windowStatus(string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        return response()->json($this->windowService->status($conversation));
    }
}
