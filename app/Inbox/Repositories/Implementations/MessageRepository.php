<?php

namespace App\Inbox\Repositories\Implementations;

use App\Inbox\Models\Message;
use App\Inbox\Repositories\Interfaces\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    /**
     * Get all messages for a conversation.
     */
    public function getConversationMessages(int $conversationId): Collection
    {
        return Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Find a message by ID.
     */
    public function findById(int $id): ?Message
    {
        return Message::find($id);
    }

    /**
     * Create a new message.
     */
    public function create(array $data): Message
    {
        return Message::create($data);
    }

    /**
     * Update a message.
     */
    public function update(int $id, array $data): bool
    {
        $message = $this->findById($id);
        
        if (!$message) {
            return false;
        }
        
        return $message->update($data);
    }

    /**
     * Delete a message.
     */
    public function delete(int $id): bool
    {
        $message = $this->findById($id);
        
        if (!$message) {
            return false;
        }
        
        return $message->delete();
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(int $id): bool
    {
        $message = $this->findById($id);
        
        if (!$message) {
            return false;
        }
        
        $message->is_read = true;
        return $message->save();
    }

    /**
     * Get unread messages count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return Message::where('recipient_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}
