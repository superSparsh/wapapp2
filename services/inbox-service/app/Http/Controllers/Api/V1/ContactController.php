<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationResponseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInboxContactRequest;
use App\Services\InboxContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(
        private readonly InboxContactService $contactService,
    ) {}

    public function store(StoreInboxContactRequest $request): JsonResponse
    {
        $conversation = $this->contactService->addContact(
            lineId: $request->integer('line_id'),
            linePhone: $request->string('line_phone')->trim()->toString() ?: null,
            name: $request->validated('name'),
            phone: $request->validated('phone'),
            responseType: ConversationResponseType::tryFrom((string) $request->input('response_type')) ?? ConversationResponseType::Human,
            contactId: $request->integer('contact_id') ?: null,
        );

        return response()->json([
            'ok' => true,
            'conversation_uuid' => $conversation->uuid,
        ], 201);
    }
}
