<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Services\FcmPushService;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInboxFcmPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 20;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $conversationId,
        public readonly int $messageId,
    ) {}

    public function handle(FcmPushService $push): void
    {
        $tenant = tenancy()->central(fn () => Tenant::query()->find($this->tenantId));
        if ($tenant === null) {
            return;
        }

        $alreadyOnTenant = tenancy()->initialized
            && (string) tenant('id') === (string) $this->tenantId;

        if (! $alreadyOnTenant) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            tenancy()->initialize($tenant);
        }

        try {
            $conversation = Conversation::query()->find($this->conversationId);
            $message = Message::query()->find($this->messageId);

            if ($conversation === null || $message === null) {
                return;
            }

            $push->sendNewMessageNotification($conversation, $message);
        } catch (Throwable $e) {
            Log::warning('SendInboxFcmPushJob failed', [
                'tenant_id' => $this->tenantId,
                'conversation_id' => $this->conversationId,
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (! $alreadyOnTenant && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
