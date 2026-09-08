<?php

namespace App\Inbox\Repositories\Interfaces;

use App\Inbox\Models\Message;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    /**
     * Get all messages for a conversation.
     */
    public function getConversationMessages(int $conversationId): Collection;

    /**
     * Find a message by ID.
     */
    public function findById(int $id): ?Message;

    /**
     * Create a new message.
     */
    public function create(array $data): Message;

    /**
     * Update a message.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a message.
     */
    public function delete(int $id): bool;

    /**
     * Mark a message as read.
     */
    public function markAsRead(int $id): bool;

    /**
     * Get unread messages count for a user.
     */
    public function getUnreadCount(int $userId): int;
}
