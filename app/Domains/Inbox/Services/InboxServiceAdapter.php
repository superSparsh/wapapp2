<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Http\Requests\AssignInboxConversationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxContactRequest;
use App\Domains\Inbox\Http\Requests\SendInboxFlowRequest;
use App\Domains\Inbox\Http\Requests\SendInboxInteractiveComposerRequest;
use App\Domains\Inbox\Http\Requests\SendInboxLocationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMediaRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Domains\Inbox\Http\Requests\SendInboxStickerRequest;
use App\Domains\Inbox\Http\Requests\SendInboxTemplateRequest;
use App\Domains\Inbox\Http\Requests\StoreInboxContactRequest;
use App\Domains\Inbox\Http\Requests\ToggleInboxResponseTypeRequest;
use App\Domains\Templates\Support\InteractiveMessagePayloadBuilder;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Enums\ConversationResponseType;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\InteractiveMessage;
use App\Models\Message;
use App\Models\WhatsappFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxServiceAdapter
{
    public function __construct(
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

    public function threads(Request $request): JsonResponse
    {
        $line = $this->localInboxService->resolveActiveLine($request);
        $filters = $this->localInboxService->filtersFromRequest($request);

        $payload = $this->localQueryService->paginateThreads(
            line: $line,
            search: $filters['search'],
            unreadOnly: $filters['unread_only'],
            lookbackDays: $filters['lookback_days'],
            cursor: $filters['cursor'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
        );
        $payload['unread_total'] = $this->localQueryService->totalUnreadCount();

        return response()->json($payload);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json($this->localQueryService->unreadSnapshot());
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);
        $filters = $this->localInboxService->filtersFromRequest($request);

        return response()->json($this->localMessageService->paginateMessages(
            conversation: $conversation,
            beforeId: $request->integer('before_id') ?: null,
            lookbackDays: $filters['lookback_days'],
        ));
    }

    public function sendMessage(SendInboxMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        $message = $this->localOutboundService->sendText($conversation, $request->validated('body'));

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendMedia(SendInboxMediaRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        $message = $this->localOutboundService->sendMedia(
            conversation: $conversation,
            file: $request->file('file'),
            mediaType: $request->validated('media_type'),
            caption: $request->validated('caption'),
            sendImmediately: true,
        );

        $message->refresh();

        if ($message->status === MessageStatus::Failed) {
            return response()->json([
                'message' => (string) ($message->failed_reason ?: 'Unable to send media via WhatsApp.'),
            ], 422);
        }

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendTemplate(SendInboxTemplateRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        $message = $this->localOutboundService->sendTemplate(
            conversation: $conversation,
            templateCode: $request->validated('template_code'),
            templateParams: $request->validated('template_params') ?? [],
            language: $request->validated('language'),
            sendImmediately: true,
        );

        $message->refresh();

        if ($message->status === MessageStatus::Failed) {
            return response()->json([
                'message' => (string) ($message->failed_reason ?: 'Unable to send template via WhatsApp.'),
            ], 422);
        }

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendLocation(SendInboxLocationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

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

        $message = $this->localOutboundService->sendSticker(
            conversation: $conversation,
            file: $request->file('file'),
            sendImmediately: true,
        );

        $message->refresh();

        if ($message->status === MessageStatus::Failed) {
            return response()->json([
                'message' => (string) ($message->failed_reason ?: 'Unable to send sticker via WhatsApp.'),
            ], 422);
        }

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendContact(SendInboxContactRequest $request, Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        $message = $this->localOutboundService->sendContact(
            conversation: $conversation,
            contact: $request->validated(),
        );

        $message->refresh();

        if ($message->status === MessageStatus::Failed) {
            return response()->json([
                'message' => (string) ($message->failed_reason ?: 'Unable to send contact via WhatsApp.'),
            ], 422);
        }

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendFlow(
        SendInboxFlowRequest $request,
        Conversation $conversation,
    ): JsonResponse {
        $this->localInboxService->authorizeConversation($conversation);

        $flow = WhatsappFlow::query()->findOrFail((int) $request->validated('flow_id'));

        if (! $flow->isActive() || blank($flow->meta_flow_id)) {
            return response()->json([
                'message' => 'Select a published WhatsApp Flow with a Meta Flow ID.',
            ], 422);
        }

        $interactive = app(WhatsappFlowInteractiveService::class)
            ->buildFlowInteractiveContent(
                $flow,
                (string) $request->validated('body'),
                (string) $request->validated('flow_cta'),
            );

        $message = $this->localOutboundService->sendInteractive(
            $conversation,
            $interactive,
            previewBody: (string) $request->validated('body'),
            enforceWindow: true,
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendInteractiveMessage(Conversation $conversation, string $interactiveMessageId): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        $messageModel = InteractiveMessage::query()
            ->where(function ($query) use ($interactiveMessageId): void {
                $query->where('uuid', $interactiveMessageId);
                if (is_numeric($interactiveMessageId)) {
                    $query->orWhere('id', (int) $interactiveMessageId);
                }
            })
            ->first();

        if ($messageModel === null) {
            return response()->json(['message' => 'Free template message not found.'], 404);
        }

        $interactive = app(InteractiveMessagePayloadBuilder::class)
            ->forMessage($messageModel);

        if (($interactive['type'] ?? '') === 'button' && empty($interactive['action']['buttons'] ?? [])) {
            return response()->json(['message' => 'This free template has no buttons configured.'], 422);
        }

        if (($interactive['type'] ?? '') === 'list' && empty($interactive['action']['sections'] ?? [])) {
            return response()->json(['message' => 'This free template has no list sections configured.'], 422);
        }

        $message = $this->localOutboundService->sendInteractive(
            $conversation,
            $interactive,
            previewBody: (string) ($interactive['body']['text'] ?? $messageModel->name),
            enforceWindow: true,
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendInteractiveComposer(
        SendInboxInteractiveComposerRequest $request,
        Conversation $conversation,
    ): JsonResponse {
        $this->localInboxService->authorizeConversation($conversation);

        $validated = $request->validated();
        $type = (string) $validated['type'];
        $headerText = trim((string) ($validated['header'] ?? $validated['header_text'] ?? ''));

        $flat = [
            'body' => (string) ($validated['body'] ?? ''),
            'footer' => (string) ($validated['footer'] ?? ''),
            'header' => $headerText !== '' ? ['type' => 'text', 'text' => $headerText] : ['type' => 'none', 'text' => ''],
            'buttons' => $validated['buttons'] ?? [],
            'list_button_text' => (string) ($validated['list_button_text'] ?? $validated['button_text'] ?? 'View options'),
            'list_sections' => $validated['sections'] ?? [],
            'catalog_id' => (string) ($validated['catalog_id'] ?? ''),
            'product_retailer_id' => (string) ($validated['product_retailer_id'] ?? ''),
            'product_retailer_ids' => $validated['product_retailer_ids'] ?? [],
            'section_title' => (string) ($validated['section_title'] ?? ''),
            'button_text' => (string) ($validated['button_text'] ?? ''),
            'url' => (string) ($validated['url'] ?? ''),
            'country' => (string) ($validated['country'] ?? 'IN'),
        ];

        $interactive = app(InteractiveMessagePayloadBuilder::class)
            ->fromFlat($type, $flat);

        if ($type === 'button' && empty($interactive['action']['buttons'] ?? [])) {
            return response()->json(['message' => 'Add at least one reply button.'], 422);
        }

        if ($type === 'list' && empty($interactive['action']['sections'] ?? [])) {
            return response()->json(['message' => 'Add at least one list option.'], 422);
        }

        if ($type === 'cta_url' && blank($interactive['action']['parameters']['url'] ?? null)) {
            return response()->json(['message' => 'Enter a valid website URL.'], 422);
        }

        $message = $this->localOutboundService->sendInteractive(
            $conversation,
            $interactive,
            previewBody: (string) ($interactive['body']['text'] ?? $type),
            enforceWindow: true,
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        $this->localMessageService->markRead($conversation);

        return response()->json(['ok' => true]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->localInboxService->deleteConversation($conversation);

        return response()->json([
            'ok' => true,
            'redirect' => route('inbox.index'),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $line = $this->localInboxService->resolveActiveLine($request);
        $filters = $this->localInboxService->filtersFromRequest($request);

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

        $responseType = $aiEnabled ? ConversationResponseType::Ai : ConversationResponseType::Human;
        $conversation = $this->localResponseTypeService->setForConversation($conversation, $responseType);

        return response()->json([
            'ok' => true,
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
        ]);
    }

    public function toggleAllResponseType(ToggleInboxResponseTypeRequest $request): JsonResponse
    {
        $line = $this->localInboxService->resolveActiveLine($request);
        $filters = $this->localInboxService->filtersFromRequest($request);
        $aiEnabled = $request->boolean('ai_enabled');
        session(['inbox.ai_for_all' => $aiEnabled]);

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

        return response()->json($this->localWindowService->status($conversation));
    }

    public function storeContact(StoreInboxContactRequest $request): JsonResponse
    {
        $line = $this->localInboxService->resolveActiveLine($request);

        $conversation = $this->localContactService->addContact(
            line: $line,
            name: $request->validated('name'),
            phone: $request->fullPhone(),
            responseType: ConversationResponseType::from($request->validated('response_type')),
        );

        return response()->json([
            'ok' => true,
            'conversation_uuid' => $conversation->uuid,
            'redirect' => route('inbox.show', $conversation),
        ], 201);
    }

    public function exportConversation(Conversation $conversation): StreamedResponse
    {
        $this->localInboxService->authorizeConversation($conversation);

        return $this->localExportService->exportConversation($conversation);
    }

    public function exportAll(Request $request): StreamedResponse
    {
        $line = $this->localInboxService->resolveActiveLine($request);
        $filters = $this->localInboxService->filtersFromRequest($request);

        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;
        $excludePhones = $this->parseSkipPhones($request->input('skip_phones'));

        return $this->localExportService->exportFilteredThreads(
            line: $line,
            search: $filters['search'],
            unreadOnly: $filters['unread_only'],
            lookbackDays: $filters['lookback_days'],
            scope: $filters['scope'],
            assigneeFilter: $filters['assignee_filter'],
            from: $from,
            to: $to,
            excludePhones: $excludePhones,
        );
    }

    /**
     * @return array<int, string>
     */
    private function parseSkipPhones(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        return preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    private function messagePayload(Message $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];

        return [
            'message' => [
                'uuid' => $message->uuid,
                'body' => $message->body,
                'direction' => $message->direction->value,
                'status' => $message->status->value,
                'message_type' => $message->message_type->value,
                'time' => \App\Domains\Inbox\Support\InboxPresenter::relativeTime($message->created_at),
                'is_outbound' => true,
                'media_url' => \App\Domains\Inbox\Support\InboxPresenter::displayMediaUrl($metadata),
                'file_name' => $metadata['file_name'] ?? null,
                'latitude' => $metadata['latitude'] ?? null,
                'longitude' => $metadata['longitude'] ?? null,
                'contacts' => $metadata['contacts'] ?? null,
                'template_code' => $metadata['template_code'] ?? null,
                'template_name' => $metadata['template_name'] ?? null,
                'template_buttons' => $metadata['template_buttons'] ?? [],
                'interactive' => $metadata['interactive'] ?? null,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'read_at' => $message->read_at?->toIso8601String(),
                'failed_at' => $message->failed_at?->toIso8601String(),
                'failed_reason' => $message->failed_reason,
            ],
        ];
    }
}
