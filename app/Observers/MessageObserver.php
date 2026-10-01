<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Billing\Services\TemplateWalletChargeService;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageObserver
{
    public function __construct(
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    public function created(Message $message): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $externalId = $message->external_message_id;
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '' || ! is_string($externalId) || $externalId === '') {
            return;
        }

        $this->registryService->indexMessage($tenantId, $externalId, (int) $message->id);
    }

    /**
     * Safety net: charge wallet when status flips to Delivered/Read
     * (covers paths that update status outside DeliveryStatusHandler).
     * Service Sent charges run from the outbound gateway after media metadata is stable.
     */
    public function updated(Message $message): void
    {
        if (! tenancy()->initialized) {
            Log::warning('MessageObserver wallet charge skipped: tenancy not initialized', [
                'message_id' => $message->id,
            ]);

            return;
        }

        if (! $message->wasChanged('status')) {
            return;
        }

        $status = $message->status instanceof MessageStatus
            ? $message->status
            : MessageStatus::tryFrom((string) $message->status);

        if ($status !== MessageStatus::Delivered && $status !== MessageStatus::Read) {
            return;
        }

        $deliveryStatus = $status === MessageStatus::Read ? 'Read' : 'Delivered';

        try {
            $message->loadMissing('conversation');
            app(TemplateWalletChargeService::class)->chargeIfDelivered(
                message: $message,
                deliveryStatus: $deliveryStatus,
                whatsappLineId: $message->conversation?->whatsapp_line_id
                    ? (int) $message->conversation->whatsapp_line_id
                    : null,
            );
        } catch (Throwable $e) {
            Log::warning('MessageObserver wallet charge failed', [
                'message_id' => $message->id,
                'status' => $deliveryStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
