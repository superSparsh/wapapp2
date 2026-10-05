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

        $this->indexProviderMessageId($message, $externalId, $tenantId);
    }

    /**
     * Safety net: charge wallet when status flips to Delivered/Read
     * (covers paths that update status outside DeliveryStatusHandler).
     * Service Sent charges run from the outbound gateway after media metadata is stable.
     */
    public function updated(Message $message): void
    {
        if (! tenancy()->initialized) {
            if ($message->wasChanged('status')) {
                Log::warning('MessageObserver wallet charge skipped: tenancy not initialized', [
                    'message_id' => $message->id,
                ]);
            }

            return;
        }

        if ($message->wasChanged('external_message_id')) {
            $this->indexProviderMessageId(
                $message,
                $message->external_message_id,
                tenant('id'),
            );
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

    private function indexProviderMessageId(Message $message, mixed $externalId, mixed $tenantId): void
    {
        if (! is_string($externalId) || $externalId === '' || str_starts_with($externalId, 'local_')) {
            return;
        }

        if ($tenantId === null || $tenantId === '') {
            return;
        }

        $this->registryService->indexMessage((string) $tenantId, $externalId, (int) $message->id);
    }
}
