<?php

namespace App\Inbox\Http\Controllers;

use App\Inbox\Models\Conversation;
use App\Inbox\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    protected $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Display a listing of the conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $conversations = $this->conversationService->getUserConversations($userId);
        
        return response()->json([
            'data' => $conversations,
            'message' => 'Conversations retrieved successfully'
        ]);
    }

    /**
     * Store a newly created conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'participant_id' => 'required|exists:users,id',
            'subject' => 'nullable|string|max:255',
        ]);

        $conversation = $this->conversationService->createConversation($validatedData);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation created successfully'
        ], 201);
    }

    /**
     * Display the specified conversation.
     */
    public function show(int $id): JsonResponse
    {
        $conversation = $this->conversationService->getConversation($id);
        
        if (!$conversation) {
            return response()->json([
                'error' => 'Conversation not found'
            ], 404);
        }

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation retrieved successfully'
        ]);
    }

    /**
     * Update the specified conversation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validatedData = $request->validate([
            'subject' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $updated = $this->conversationService->updateConversation($id, $validatedData);
        
        if (!$updated) {
            return response()->json([
                'error' => 'Conversation not found'
            ], 404);
        }

        $conversation = $this->conversationService->getConversation($id);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation updated successfully'
        ]);
    }

    /**
     * Remove the specified conversation.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->conversationService->deleteConversation($id);
        
        if (!$deleted) {
            return response()->json([
                'error' => 'Conversation not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Conversation deleted successfully'
        ]);
    }

    /**
     * Show conversation with messages.
     */
    public function showWithMessages(int $id): JsonResponse
    {
        $conversation = $this->conversationService->getConversationWithMessages($id);
        
        if (!$conversation) {
            return response()->json([
                'error' => 'Conversation not found'
            ], 404);
        }

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation with messages retrieved successfully'
        ]);
    }
}
