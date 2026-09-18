<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Jobs;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOutboundMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(OutboundMessageGateway $gateway, MaintenanceModeService $maintenance): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        if (! $maintenance->moduleEnabled('outbound_messages')) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'failed_at' => now(),
                'failed_reason' => 'Outbound messaging is temporarily disabled (maintenance).',
            ])->save();

            try {
                app(\App\Domains\Inbox\Services\InboxBroadcastService::class)
                    ->messageStatusUpdated($message->refresh());
            } catch (\Throwable) {
                // Best-effort realtime status.
            }

            return;
        }

        $gateway->send($message);
    }
}
