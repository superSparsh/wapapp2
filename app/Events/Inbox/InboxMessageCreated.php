<?php

declare(strict_types=1);

namespace App\Events\Inbox;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboxMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $thread
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationUuid,
        public readonly array $message,
        public readonly array $thread,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inbox.'.$this->tenantId),
            new PrivateChannel('inbox.'.$this->tenantId.'.conversation.'.$this->conversationUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_uuid' => $this->conversationUuid,
            'message' => $this->message,
            'thread' => $this->thread,
        ];
    }
}
