<?php

namespace App\Inbox\Http\Controllers;

use App\Inbox\Models\Message;
use App\Inbox\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    protected $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * Display a listing of the messages.
     */
    public function index(Request $request): JsonResponse
    {
        $conversationId = $request->query('conversation_id');
        
        if ($conversationId) {
            $messages = $this->messageService->getConversationMessages($conversationId);
        } else {
            // Return paginated results if no conversation specified
            $messages = Message::orderBy('created_at', 'desc')->paginate(15);
        }
        
        return response()->json([
            'data' => $messages,
            'message' => 'Messages retrieved successfully'
        ]);
    }

    /**
     * Store a newly created message.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'sender_id' => 'required|exists:users,id',
            'recipient_id' => 'required|exists:users,id',
            'content' => 'required|string',
            'message_type' => 'nullable|string|in:text,image,file',
        ]);

        $message = $this->messageService->createMessage($validatedData);

        return response()->json([
            'data' => $message,
            'message' => 'Message created successfully'
        ], 201);
    }

    /**
     * Display the specified message.
     */
    public function show(int $id): JsonResponse
    {
        $message = $this->messageService->getMessage($id);
        
        if (!$message) {
            return response()->json([
                'error' => 'Message not found'
            ], 404);
        }

        return response()->json([
            'data' => $message,
            'message' => 'Message retrieved successfully'
        ]);
    }

    /**
     * Update the specified message.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validatedData = $request->validate([
            'content' => 'nullable|string',
            'message_type' => 'nullable|string|in:text,image,file',
        ]);

        $updated = $this->messageService->updateMessage($id, $validatedData);
        
        if (!$updated) {
            return response()->json([
                'error' => 'Message not found'
            ], 404);
        }

        $message = $this->messageService->getMessage($id);

        return response()->json([
            'data' => $message,
            'message' => 'Message updated successfully'
        ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->messageService->deleteMessage($id);
        
        if (!$deleted) {
            return response()->json([
                'error' => 'Message not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Message deleted successfully'
        ]);
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $marked = $this->messageService->markAsRead($id);
        
        if (!$marked) {
            return response()->json([
                'error' => 'Message not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Message marked as read successfully'
        ]);
    }
}
