<?php

namespace App\Inbox\Repositories\Interfaces;

use App\Inbox\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;

interface ConversationRepositoryInterface
{
    /**
     * Get all conversations for a user.
     */
    public function getUserConversations(int $userId): Collection;

    /**
     * Find a conversation by ID.
     */
    public function findById(int $id): ?Conversation;

    /**
     * Create a new conversation.
     */
    public function create(array $data): Conversation;

    /**
     * Update a conversation.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a conversation.
     */
    public function delete(int $id): bool;

    /**
     * Get conversations with pagination.
     */
    public function paginate(int $perPage = 15, int $page = 1): \Illuminate\Pagination\LengthAwarePaginator;
}
