<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Jobs;

use App\Domains\Inbox\Services\InboxOutboundService;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-sends WhatsApp typing indicator during long typing delays (legacy repeatTyping).
 */
class SendTypingIndicatorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(
        public readonly int $conversationId,
    ) {}

    public function handle(InboxOutboundService $outbound): void
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        $outbound->sendTypingIndicator($conversation);
    }
}
