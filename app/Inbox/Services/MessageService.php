<?php

namespace App\Inbox\Services;

use App\Inbox\Models\Message;
use App\Inbox\Repositories\Interfaces\MessageRepositoryInterface;

class MessageService
{
    protected $messageRepository;

    public function __construct(MessageRepositoryInterface $messageRepository)
    {
        $this->messageRepository = $messageRepository;
    }

    /**
     * Get all messages for a conversation.
     */
    public function getConversationMessages(int $conversationId)
    {
        return $this->messageRepository->getConversationMessages($conversationId);
    }

    /**
     * Create a new message.
     */
    public function createMessage(array $data)
    {
        return $this->messageRepository->create($data);
    }

    /**
     * Get a message by ID.
     */
    public function getMessage(int $id)
    {
        return $this->messageRepository->findById($id);
    }

    /**
     * Update a message.
     */
    public function updateMessage(int $id, array $data)
    {
        return $this->messageRepository->update($id, $data);
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(int $id)
    {
        return $this->messageRepository->delete($id);
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(int $id)
    {
        return $this->messageRepository->markAsRead($id);
    }

    /**
     * Get unread messages count for a user.
     */
    public function getUnreadCount(int $userId)
    {
        return $this->messageRepository->getUnreadCount($userId);
    }
}
