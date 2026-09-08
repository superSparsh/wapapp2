<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendInboxLocationRequest;
use App\Http\Requests\SendInboxMediaRequest;
use App\Http\Requests\SendInboxMessageRequest;
use App\Http\Requests\SendInboxStickerRequest;
use App\Http\Requests\SendInboxTemplateRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\InboxMessageService;
use App\Services\InboxOutboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly InboxMessageService $messageService,
        private readonly InboxOutboundService $outboundService,
    ) {}

    public function index(Request $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        $beforeId = $request->integer('before_id') ?: null;
        $lookbackDays = $request->integer('lookback_days') ?: $request->integer('days') ?: null;
        $limit = $request->integer('limit') ?: null;

        $result = $this->messageService->paginateMessages(
            conversation: $conversation,
            beforeId: $beforeId,
            lookbackDays: $lookbackDays,
            limit: $limit,
        );

        return response()->json($result);
    }

    public function sendMessage(SendInboxMessageRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();
        $message = $this->outboundService->sendText($conversation, $request->validated('body'));

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendMedia(SendInboxMediaRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        if ($request->hasFile('file')) {
            $message = $this->outboundService->sendMedia(
                conversation: $conversation,
                file: $request->file('file'),
                mediaType: $request->validated('media_type'),
                caption: $request->validated('caption'),
            );
        } else {
            $message = $this->outboundService->sendMediaFromUrl(
                conversation: $conversation,
                mediaUrl: (string) $request->validated('media_url'),
                mediaType: (string) $request->validated('media_type'),
                caption: $request->validated('caption'),
                fileName: $request->validated('file_name'),
                fileType: $request->validated('file_type'),
            );
        }

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendTemplate(SendInboxTemplateRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();
        $message = $this->outboundService->sendTemplate(
            conversation: $conversation,
            templateCode: $request->validated('template_code'),
            templateParams: $request->validated('template_params') ?? [],
            language: $request->validated('language'),
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendLocation(SendInboxLocationRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();
        $message = $this->outboundService->sendLocation(
            conversation: $conversation,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
        );

        return response()->json($this->messagePayload($message), 201);
    }

    public function sendSticker(SendInboxStickerRequest $request, string $conversationUuid): JsonResponse
    {
        $conversation = Conversation::query()->where('uuid', $conversationUuid)->firstOrFail();

        if ($request->hasFile('file')) {
            $message = $this->outboundService->sendSticker(
                conversation: $conversation,
                file: $request->file('file'),
            );
        } else {
            $message = $this->outboundService->sendMediaFromUrl(
                conversation: $conversation,
                mediaUrl: (string) $request->validated('media_url'),
                mediaType: 'sticker',
            );
        }

        return response()->json($this->messagePayload($message), 201);
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
