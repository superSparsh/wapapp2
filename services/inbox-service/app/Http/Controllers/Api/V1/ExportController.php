<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\InboxExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly InboxExportService $exportService,
    ) {}

    public function exportConversation(string $conversationUuid): StreamedResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        return $this->exportService->exportConversation($conversation);
    }

    public function exportAll(Request $request): StreamedResponse
    {
        $lineId = $request->integer('line_id');
        abort_if($lineId <= 0, 422, 'Valid line_id is required.');

        $search = $request->string('search')->trim()->toString() ?: $request->string('q')->trim()->toString() ?: null;
        $unreadOnly = $request->boolean('unread_only') || $request->string('scope')->toString() === 'unread';
        $lookbackDays = $request->integer('days') ?: $request->integer('lookback_days') ?: null;
        $scope = $request->string('scope')->trim()->toString() ?: null;

        $assigneeFilter = null;
        if ($request->string('assignee')->toString() === 'unassigned') {
            $assigneeFilter = ['unassigned' => true];
        } elseif ($request->has('assigned_user_id')) {
            $assigneeFilter = ['user_id' => $request->integer('assigned_user_id')];
        } elseif ($request->has('assigned_team_member_id')) {
            $assigneeFilter = ['team_member_id' => $request->integer('assigned_team_member_id')];
        }

        return $this->exportService->exportFilteredThreads(
            lineId: $lineId,
            search: $search,
            unreadOnly: $unreadOnly,
            lookbackDays: $lookbackDays,
            scope: $scope,
            assigneeFilter: $assigneeFilter,
        );
    }
}
