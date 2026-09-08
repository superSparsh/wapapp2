<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Contracts\InboxServiceClientInterface;
use App\Domains\Inbox\Http\Requests\AssignInboxConversationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxLocationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMediaRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Domains\Inbox\Http\Requests\SendInboxStickerRequest;
use App\Domains\Inbox\Http\Requests\SendInboxTemplateRequest;
use App\Domains\Inbox\Http\Requests\StoreInboxContactRequest;
use App\Domains\Inbox\Http\Requests\ToggleInboxResponseTypeRequest;
use App\Domains\Inbox\Support\InboxActor;
use App\Enums\ConversationResponseType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WhatsappLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxServiceAdapter
{
    public function __construct(
        private readonly InboxServiceClientInterface $client,
        private readonly InboxService $localInboxService,
        private readonly InboxQueryService $localQueryService,
        private readonly InboxMessageService $localMessageService,
        private readonly InboxOutboundService $localOutboundService,
        private readonly InboxAssignmentService $localAssignmentService,
        private readonly InboxResponseTypeService $localResponseTypeService,
        private readonly InboxContactService $localContactService,
        private readonly InboxExportService $localExportService,
        private readonly MessagingWindowService $localWindowService,
        private readonly InboxBroadcastService $broadcastService,
    ) {}

    public function isMicroserviceEnabled(): bool
    {
        return (bool) config('inbox-service.enabled', false);
    }

    public function shouldFallback(): bool
    {
        return (bool) config('inbox-service.fallback_to_local', true);
    }

    public function threads(Request $request): JsonResponse
    {
        $line = $this->localInboxService->requireDefaultLine();
        $filters = $this->localInboxService->filtersFromRequest($request);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->getThreads((int) $line->id, [
                    'search' => $filters['search'],
                    'unread_only' => $filters['unread_only'],
                    'lookback_days' => $filters['lookback_days'],
                    'cursor' => $filters['cursor'],
                    'scope' => $filters['scope'],
                    'assignee' => $filters['assignee'],
                ]);

                return response()->json($payload);
            } catch (\Throwable $e) {
                $this->logFallback('threads', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return response()->json($this->localQueryService->paginateThreads(
            line: $line,
            search: $filters['search'],
            unreadOnly: $filters['unread_only'],
            lookbackDays: $filters['lookback_days'],
            cursor: $filters['cursor'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
        ));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);
        $filters = $this->localInboxService->filtersFromRequest($request);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->getMessages($conversation->uuid, [
                    'before_id' => $request->integer('before_id') ?: null,
                    'lookback_days' => $filters['lookback_days'],
                ]);

                return response()->json($payload);
            } catch (\Throwable $e) {
                $this->logFallback('messages', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return response()->json($this->localMessageService->paginateMessages(
            conversation: $conversation,
            beforeId: $request->integer('before_id') ?: null,
            lookbackDays: $filters['lookback_days'],
        ));
    }

    public function sendMessage(SendInboxMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->sendMessage($conversation->uuid, $request->validated('body'));

                return response()->json($payload, 201);
            } catch (\Throwable $e) {
                $this->logFallback('sendMessage', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $message = $this->localOutboundService->sendText($conversation, $request->validated('body'));

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendMedia(SendInboxMediaRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->sendMedia(
                    conversationUuid: $conversation->uuid,
                    file: $request->file('file'),
                    mediaType: $request->validated('media_type'),
                    caption: $request->validated('caption'),
                );

                return response()->json($payload, 201);
            } catch (\Throwable $e) {
                $this->logFallback('sendMedia', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $message = $this->localOutboundService->sendMedia(
            conversation: $conversation,
            file: $request->file('file'),
            mediaType: $request->validated('media_type'),
            caption: $request->validated('caption'),
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendTemplate(SendInboxTemplateRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->sendTemplate(
                    conversationUuid: $conversation->uuid,
                    templateCode: $request->validated('template_code'),
                    templateParams: $request->validated('template_params') ?? [],
                    language: $request->validated('language'),
                );

                return response()->json($payload, 201);
            } catch (\Throwable $e) {
                $this->logFallback('sendTemplate', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $message = $this->localOutboundService->sendTemplate(
            conversation: $conversation,
            templateCode: $request->validated('template_code'),
            templateParams: $request->validated('template_params') ?? [],
            language: $request->validated('language'),
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendLocation(SendInboxLocationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->sendLocation(
                    conversationUuid: $conversation->uuid,
                    latitude: (float) $request->validated('latitude'),
                    longitude: (float) $request->validated('longitude'),
                );

                return response()->json($payload, 201);
            } catch (\Throwable $e) {
                $this->logFallback('sendLocation', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $message = $this->localOutboundService->sendLocation(
            conversation: $conversation,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendSticker(SendInboxStickerRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $payload = $this->client->sendSticker(
                    conversationUuid: $conversation->uuid,
                    file: $request->file('file'),
                );

                return response()->json($payload, 201);
            } catch (\Throwable $e) {
                $this->logFallback('sendSticker', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $message = $this->localOutboundService->sendSticker(
            conversation: $conversation,
            file: $request->file('file'),
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $this->client->markRead($conversation->uuid);
                $this->localMessageService->markRead($conversation);

                return response()->json(['ok' => true]);
            } catch (\Throwable $e) {
                $this->logFallback('markRead', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $this->localMessageService->markRead($conversation);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $line = $this->localInboxService->requireDefaultLine();
        $filters = $this->localInboxService->filtersFromRequest($request);

        if ($this->isMicroserviceEnabled()) {
            try {
                $updated = $this->client->markAllRead((int) $line->id, [
                    'lookback_days' => $filters['lookback_days'],
                    'scope' => $filters['scope'],
                    'assignee' => $filters['assignee'],
                ]);

                $this->localMessageService->markAllReadForLine(
                    line: $line,
                    queryService: $this->localQueryService,
                    lookbackDays: $filters['lookback_days'],
                    scope: $filters['scope'],
                    assigneeFilter: $filters['assignee_filter'],
                );

                return response()->json(['ok' => true, 'updated' => $updated]);
            } catch (\Throwable $e) {
                $this->logFallback('markAllRead', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $updated = $this->localMessageService->markAllReadForLine(
            line: $line,
            queryService: $this->localQueryService,
            lookbackDays: $filters['lookback_days'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
        );

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    public function assign(AssignInboxConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);
        $assigneeKey = $request->validated('assignee');

        $userId = null;
        $teamMemberId = null;

        if ($assigneeKey && str_contains($assigneeKey, ':')) {
            [$type, $uuid] = explode(':', $assigneeKey, 2);
            if ($type === 'user') {
                $userId = User::query()->where('uuid', $uuid)->value('id');
            } elseif ($type === 'member') {
                $teamMemberId = TeamMember::query()->where('uuid', $uuid)->value('id');
            }
        }

        if ($this->isMicroserviceEnabled()) {
            try {
                $this->client->assign(
                    conversationUuid: $conversation->uuid,
                    assigneeKey: $assigneeKey,
                    userId: $userId ? (int) $userId : null,
                    teamMemberId: $teamMemberId ? (int) $teamMemberId : null,
                );
            } catch (\Throwable $e) {
                $this->logFallback('assign', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $conversation = $this->localAssignmentService->assign($conversation, $assigneeKey);
        $conversation->load(['assignedUser:id,uuid,name,first_name', 'assignedTeamMember:id,uuid,first_name,last_name,email']);

        $resolvedKey = null;
        if ($conversation->assignedUser) {
            $resolvedKey = 'user:'.$conversation->assignedUser->uuid;
        } elseif ($conversation->assignedTeamMember) {
            $resolvedKey = 'member:'.$conversation->assignedTeamMember->uuid;
        }

        return response()->json([
            'ok' => true,
            'assignee' => $resolvedKey,
        ]);
    }

    public function toggleResponseType(ToggleInboxResponseTypeRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);
        $aiEnabled = $request->boolean('ai_enabled');

        if ($this->isMicroserviceEnabled()) {
            try {
                $this->client->toggleResponseType($conversation->uuid, $aiEnabled);
            } catch (\Throwable $e) {
                $this->logFallback('toggleResponseType', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $responseType = $aiEnabled ? ConversationResponseType::Ai : ConversationResponseType::Human;
        $conversation = $this->localResponseTypeService->setForConversation($conversation, $responseType);

        return response()->json([
            'ok' => true,
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
        ]);
    }

    public function toggleAllResponseType(ToggleInboxResponseTypeRequest $request): JsonResponse
    {
        $line = $this->localInboxService->requireDefaultLine();
        $filters = $this->localInboxService->filtersFromRequest($request);
        $aiEnabled = $request->boolean('ai_enabled');

        if ($this->isMicroserviceEnabled()) {
            try {
                $updated = $this->client->toggleAllResponseType((int) $line->id, $aiEnabled, [
                    'lookback_days' => $filters['lookback_days'],
                    'scope' => $filters['scope'],
                ]);

                $responseType = $aiEnabled ? ConversationResponseType::Ai : ConversationResponseType::Human;
                $this->localResponseTypeService->setForAllOnLine(
                    line: $line,
                    responseType: $responseType,
                    lookbackDays: $filters['lookback_days'],
                    scope: $filters['scope'],
                    assigneeFilter: $filters['assignee_filter'],
                );

                return response()->json([
                    'ok' => true,
                    'updated' => $updated,
                    'ai_enabled' => $aiEnabled,
                ]);
            } catch (\Throwable $e) {
                $this->logFallback('toggleAllResponseType', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $responseType = $aiEnabled ? ConversationResponseType::Ai : ConversationResponseType::Human;
        $updated = $this->localResponseTypeService->setForAllOnLine(
            line: $line,
            responseType: $responseType,
            lookbackDays: $filters['lookback_days'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
        );

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'ai_enabled' => $responseType->isAi(),
        ]);
    }

    public function windowStatus(Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                $status = $this->client->getWindowStatus($conversation->uuid);

                return response()->json($status);
            } catch (\Throwable $e) {
                $this->logFallback('windowStatus', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return response()->json($this->localWindowService->status($conversation));
    }

    public function storeContact(StoreInboxContactRequest $request): JsonResponse
    {
        $line = $this->localInboxService->requireDefaultLine();

        $conversation = $this->localContactService->addContact(
            line: $line,
            name: $request->validated('name'),
            phone: $request->fullPhone(),
            responseType: ConversationResponseType::from($request->validated('response_type')),
        );

        if ($this->isMicroserviceEnabled()) {
            try {
                $this->client->storeContact(
                    lineId: (int) $line->id,
                    name: $request->validated('name'),
                    phone: $request->fullPhone(),
                    linePhone: $line->phone,
                    responseType: $request->validated('response_type'),
                    contactId: (int) $conversation->contact_id,
                );
            } catch (\Throwable $e) {
                $this->logFallback('storeContact', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return response()->json([
            'ok' => true,
            'conversation_uuid' => $conversation->uuid,
            'redirect' => route('inbox.show', $conversation),
        ], 201);
    }

    public function exportConversation(Conversation $conversation): StreamedResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        if ($this->isMicroserviceEnabled()) {
            try {
                return $this->client->exportConversation($conversation->uuid);
            } catch (\Throwable $e) {
                $this->logFallback('exportConversation', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localExportService->exportConversation($conversation);
    }

    public function exportAll(Request $request): StreamedResponse
    {
        $line = $this->localInboxService->requireDefaultLine();
        $filters = $this->localInboxService->filtersFromRequest($request);

        if ($this->isMicroserviceEnabled()) {
            try {
                return $this->client->exportAll((int) $line->id, [
                    'search' => $filters['search'],
                    'unread_only' => $filters['unread_only'],
                    'lookback_days' => $filters['lookback_days'],
                    'scope' => $filters['scope'],
                    'assignee' => $filters['assignee'],
                ]);
            } catch (\Throwable $e) {
                $this->logFallback('exportAll', $e);
                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localExportService->exportFilteredThreads(
            line: $line,
            search: $filters['search'],
            unreadOnly: $filters['unread_only'],
            lookbackDays: $filters['lookback_days'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
        );
    }

    private function logFallback(string $operation, \Throwable $e): void
    {
        Log::warning("Inbox microservice {$operation} call failed, falling back to local monolith implementation", [
            'operation' => $operation,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    private function messagePayload(Message $message): array
    {
        return [
            'message' => [
                'uuid' => $message->uuid,
                'body' => $message->body,
                'direction' => $message->direction->value,
                'status' => $message->status->value,
                'message_type' => $message->message_type->value,
                'is_outbound' => true,
            ],
        ];
    }
}
