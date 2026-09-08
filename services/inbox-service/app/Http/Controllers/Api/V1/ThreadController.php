<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InboxQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function __construct(
        private readonly InboxQueryService $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $lineId = $request->integer('line_id') ?: null;
        $search = $request->string('search')->trim()->toString() ?: $request->string('q')->trim()->toString() ?: null;
        $unreadOnly = $request->boolean('unread_only') || $request->string('scope')->toString() === 'unread';
        $lookbackDays = $request->integer('days') ?: $request->integer('lookback_days') ?: null;
        $cursor = $request->string('cursor')->trim()->toString() ?: null;
        $limit = $request->integer('limit') ?: null;
        $scope = $request->string('scope')->trim()->toString() ?: null;

        $assignee = $request->string('assignee')->trim()->toString() ?: null;
        $assigneeFilter = null;

        if ($assignee === 'unassigned') {
            $assigneeFilter = ['unassigned' => true];
        } elseif ($request->has('assigned_user_id')) {
            $assigneeFilter = ['user_id' => $request->integer('assigned_user_id')];
        } elseif ($request->has('assigned_team_member_id')) {
            $assigneeFilter = ['team_member_id' => $request->integer('assigned_team_member_id')];
        }

        $result = $this->queryService->paginateThreads(
            lineId: $lineId,
            search: $search,
            unreadOnly: $unreadOnly,
            lookbackDays: $lookbackDays,
            cursor: $cursor,
            limit: $limit,
            scope: $scope,
            assigneeFilter: $assigneeFilter,
        );

        return response()->json($result);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $lineId = $request->integer('line_id') ?: null;

        return response()->json([
            'unread_total' => $this->queryService->totalUnreadCount($lineId),
        ]);
    }
}
