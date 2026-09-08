<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Services\Contracts\OutboundMessageGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOutboundMessageJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(OutboundMessageGateway $gateway): void
    {
        $message = Message::withoutGlobalScopes()->find($this->messageId);

        if ($message === null) {
            return;
        }

        try {
            $gateway->send($message);
        } catch (\Throwable $exception) {
            Log::error('SendOutboundMessageJob failed', [
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
