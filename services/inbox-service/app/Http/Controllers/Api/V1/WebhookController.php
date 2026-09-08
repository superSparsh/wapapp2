<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryStatusUpdateRequest;
use App\Http\Requests\RecordInboundMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\InboxContactService;
use App\Services\InboxMessageService;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function __construct(
        private readonly InboxContactService $contactService,
        private readonly InboxMessageService $messageService,
    ) {}

    public function recordInbound(RecordInboundMessageRequest $request): JsonResponse
    {
        $conversation = $this->contactService->findOrCreateConversation(
            lineId: $request->integer('line_id'),
            linePhone: $request->string('line_phone')->trim()->toString() ?: null,
            contactPhone: $request->validated('contact_phone'),
            contactName: $request->validated('contact_name'),
            contactId: $request->integer('contact_id') ?: null,
        );

        $messageType = MessageType::tryFrom((string) $request->input('message_type')) ?? MessageType::Text;

        $message = $this->messageService->recordInbound(
            conversation: $conversation,
            body: $request->validated('body'),
            externalMessageId: $request->validated('external_message_id'),
            messageType: $messageType,
            metadata: $request->validated('metadata'),
        );

        return response()->json([
            'ok' => true,
            'conversation_uuid' => $conversation->uuid,
            'message_uuid' => $message->uuid,
            'message_id' => $message->id,
        ], 201);
    }

    public function updateDeliveryStatus(DeliveryStatusUpdateRequest $request): JsonResponse
    {
        $externalId = $request->validated('external_message_id');
        $status = MessageStatus::tryFrom($request->validated('status'));

        if ($status === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid status'], 422);
        }

        $message = Message::query()->where('external_message_id', $externalId)->first();

        if ($message === null) {
            return response()->json(['ok' => false, 'message' => 'Message not found'], 404);
        }

        $now = now();
        $updates = [
            'status' => $status,
        ];

        match ($status) {
            MessageStatus::Sent => $updates['sent_at'] = $message->sent_at ?? $now,
            MessageStatus::Delivered => $updates['delivered_at'] = $message->delivered_at ?? $now,
            MessageStatus::Read => $updates['read_at'] = $message->read_at ?? $now,
            MessageStatus::Failed => [
                $updates['failed_at'] = $message->failed_at ?? $now,
                $updates['failed_reason'] = $request->validated('failed_reason') ?? 'Delivery failed',
            ],
            default => null,
        };

        $message->forceFill($updates)->save();

        return response()->json(['ok' => true]);
    }
}
