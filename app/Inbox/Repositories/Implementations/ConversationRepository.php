<?php

namespace App\Inbox\Repositories\Implementations;

use App\Inbox\Models\Conversation;
use App\Inbox\Repositories\Interfaces\ConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationRepository implements ConversationRepositoryInterface
{
    /**
     * Get all conversations for a user.
     */
    public function getUserConversations(int $userId): Collection
    {
        return Conversation::where('user_id', $userId)
            ->orWhere('participant_id', $userId)
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    /**
     * Find a conversation by ID.
     */
    public function findById(int $id): ?Conversation
    {
        return Conversation::find($id);
    }

    /**
     * Create a new conversation.
     */
    public function create(array $data): Conversation
    {
        return Conversation::create($data);
    }

    /**
     * Update a conversation.
     */
    public function update(int $id, array $data): bool
    {
        $conversation = $this->findById($id);
        
        if (!$conversation) {
            return false;
        }
        
        return $conversation->update($data);
    }

    /**
     * Delete a conversation.
     */
    public function delete(int $id): bool
    {
        $conversation = $this->findById($id);
        
        if (!$conversation) {
            return false;
        }
        
        return $conversation->delete();
    }

    /**
     * Get conversations with pagination.
     */
    public function paginate(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return Conversation::orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
