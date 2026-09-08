<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageDirection;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Carbon;

class MessagingWindowService
{
    public function isWithinServiceWindow(Conversation $conversation): bool
    {
        return $this->status($conversation)['within_window'];
    }

    /**
     * @return array{
     *     within_window: bool,
     *     expires_at: ?string,
     *     window_hours: int,
     *     last_inbound_at: ?string
     * }
     */
    public function status(Conversation $conversation): array
    {
        $windowHours = (int) config('whatsapp.service_window_hours', 24);

        $lastInboundAt = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound)
            ->orderByDesc('id')
            ->value('created_at');

        if ($lastInboundAt === null) {
            return [
                'within_window' => false,
                'expires_at' => null,
                'window_hours' => $windowHours,
                'last_inbound_at' => null,
            ];
        }

        $lastInbound = Carbon::parse($lastInboundAt);
        $expiresAt = $lastInbound->copy()->addHours($windowHours);
        $withinWindow = $expiresAt->isFuture();

        return [
            'within_window' => $withinWindow,
            'expires_at' => $withinWindow ? $expiresAt->toIso8601String() : null,
            'window_hours' => $windowHours,
            'last_inbound_at' => $lastInbound->toIso8601String(),
        ];
    }

    public function assertWithinServiceWindow(Conversation $conversation): void
    {
        abort_unless(
            $this->isWithinServiceWindow($conversation),
            422,
            'Outside the 24-hour messaging window. Send an approved template instead.',
        );
    }
}
