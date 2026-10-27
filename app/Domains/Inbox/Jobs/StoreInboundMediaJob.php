<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Jobs;

use App\Domains\Inbox\Services\InboxBroadcastService;
use App\Domains\Inbox\Services\InboxMediaService;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreInboundMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $messageId,
    ) {}

    public function handle(InboxMediaService $mediaService): void
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
            $message = Message::query()->find($this->messageId);
            if ($message === null) {
                return;
            }

            if (! $mediaService->persistInboundMessageMedia($message)) {
                return;
            }

            try {
                app(InboxBroadcastService::class)->messageStatusUpdated($message->refresh());
            } catch (Throwable) {
                // Best-effort realtime media URL update.
            }
        } catch (Throwable $e) {
            Log::warning('StoreInboundMediaJob failed', [
                'tenant_id' => $this->tenantId,
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (! $alreadyOnTenant && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
