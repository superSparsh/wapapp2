<?php

namespace App\Inbox\Services;

use App\Inbox\Models\Conversation;
use App\Inbox\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Inbox\Repositories\Interfaces\MessageRepositoryInterface;

class ConversationService
{
    protected $conversationRepository;
    protected $messageRepository;

    public function __construct(
        ConversationRepositoryInterface $conversationRepository,
        MessageRepositoryInterface $messageRepository
    ) {
        $this->conversationRepository = $conversationRepository;
        $this->messageRepository = $messageRepository;
    }

    /**
     * Get all conversations for a user.
     */
    public function getUserConversations(int $userId)
    {
        return $this->conversationRepository->getUserConversations($userId);
    }

    /**
     * Create a new conversation.
     */
    public function createConversation(array $data)
    {
        return $this->conversationRepository->create($data);
    }

    /**
     * Get a conversation by ID.
     */
    public function getConversation(int $id)
    {
        return $this->conversationRepository->findById($id);
    }

    /**
     * Update a conversation.
     */
    public function updateConversation(int $id, array $data)
    {
        return $this->conversationRepository->update($id, $data);
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(int $id)
    {
        return $this->conversationRepository->delete($id);
    }

    /**
     * Get conversation with messages.
     */
    public function getConversationWithMessages(int $id)
    {
        $conversation = $this->getConversation($id);
        
        if ($conversation) {
            $conversation->messages = $this->messageRepository->getConversationMessages($id);
        }
        
        return $conversation;
    }

    /**
     * Get unread messages count for a user.
     */
    public function getUnreadCount(int $userId)
    {
        return $this->messageRepository->getUnreadCount($userId);
    }
}
