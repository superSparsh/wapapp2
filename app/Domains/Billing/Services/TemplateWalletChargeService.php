<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Campaigns\Services\CampaignCostCalculator;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Debit wallet once per billable WhatsApp message.
 *
 * - Templates: on Delivered/Read (campaign / inbox / chatbot / …), category from metadata.
 * - Service (session) messages: same statuses as dashboard Credits “service” card —
 *   Sent / Delivered / Read (charge once; idempotent). Aligns wallet cut with the count.
 * - Utility templates: every delivery is charged (no free 24h same-conversation skip).
 */
class TemplateWalletChargeService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly CampaignCostCalculator $costCalculator,
    ) {}

    public function chargeIfDelivered(
        Message $message,
        string $deliveryStatus,
        ?CampaignRecipient $recipient = null,
    ): ?WalletTransaction {
        $statusKey = strtolower(trim($deliveryStatus));

        // Always re-read from DB so we never overwrite media_* with a stale in-memory copy
        // (Sent charge runs from MessageObserver mid-outbound gateway save).
        if ($message->exists) {
            $message->refresh();
        }

        $meta = $this->metadataArray($message);
        if (! empty($meta['wallet_charged'])) {
            return null;
        }

        // Explicit opt-out for platform alerts / non-tenant sends.
        if (array_key_exists('billable', $meta) && ! $meta['billable']) {
            return null;
        }

        $isTemplate = $message->message_type === MessageType::Template
            || (is_string($message->message_type) && strtolower($message->message_type) === 'template');
        $isService = $this->isServiceMessage($message);

        if (! $isTemplate && ! $isService) {
            return null;
        }

        // Service = dashboard parity (Sent+). Templates still wait for Delivered/Read.
        $allowedStatuses = $isService
            ? ['sent', 'delivered', 'read']
            : ['delivered', 'read'];

        if (! in_array($statusKey, $allowedStatuses, true)) {
            return null;
        }

        $message->loadMissing('conversation');

        $source = $this->resolveSource($meta, $recipient, $isService);
        $category = $isTemplate
            ? strtoupper((string) ($meta['template_category'] ?? 'MARKETING'))
            : 'SERVICE';

        $unitCost = $this->costCalculator->unitCostForCategory($category);
        if ($unitCost <= 0) {
            Log::warning('Wallet charge skipped: zero unit cost', [
                'message_id' => $message->id,
                'category' => $category,
                'source' => $source,
                'message_type' => $message->message_type instanceof MessageType
                    ? $message->message_type->value
                    : (string) $message->message_type,
            ]);

            return null;
        }

        $campaignId = (int) ($meta['campaign_id'] ?? $recipient?->campaign_id ?? 0);
        $campaign = null;
        if ($campaignId > 0) {
            $campaign = Campaign::query()->find($campaignId);
        }

        $idempotencyKey = $isTemplate
            ? 'template_message:'.$message->id
            : 'service_message:'.$message->id;
        $conversion = $this->costCalculator->conversionPrice();
        $phone = (string) (
            $recipient?->contact_phone
            ?? $meta['contact_phone']
            ?? $message->conversation?->contact_phone
            ?? ''
        );

        $description = $this->descriptionFor($source, $category, $campaign, $meta, $phone, $isService, $statusKey);

        try {
            $transaction = $this->walletService->debit(
                amount: $unitCost,
                description: $description,
                referenceType: $campaign instanceof Campaign ? Campaign::class : Message::class,
                referenceId: $campaign instanceof Campaign ? (int) $campaign->id : (int) $message->id,
                metadata: [
                    'idempotency_key' => $idempotencyKey,
                    'wallet_source' => $source,
                    'campaign_id' => $campaignId > 0 ? $campaignId : null,
                    'campaign_recipient_id' => $recipient?->id,
                    'message_id' => (int) $message->id,
                    'conversation_id' => (int) $message->conversation_id,
                    'external_message_id' => $message->external_message_id,
                    'template_category' => $category,
                    'pricing_category' => $category,
                    'template_id' => $meta['template_id'] ?? null,
                    'template_name' => $meta['template_name'] ?? null,
                    'template_code' => $meta['template_code'] ?? null,
                    'unit_cost' => $unitCost,
                    'conversion_price_used' => $conversion,
                    'contact_phone' => $phone !== '' ? $phone : null,
                    'legacy_campaign_id' => $campaignId > 0 ? $campaignId : null,
                    'legacy_category' => $source === 'opt_in' ? 'Opt-in messages' : $category,
                    'legacy_msg_id' => (string) ($message->external_message_id ?: $message->id),
                    'legacy_sender_name' => $meta['campaign_name']
                        ?? $meta['wallet_source_label']
                        ?? $campaign?->name
                        ?? $source,
                ],
                idempotencyKey: $idempotencyKey,
                allowNegative: true,
            );

            // Merge wallet flags onto the latest DB metadata (media upload may have
            // written media_url_local after this method first read the model).
            $latestMeta = $this->metadataArray($message->refresh());
            $latestMeta['wallet_charged'] = true;
            $latestMeta['wallet_transaction_id'] = $transaction->id;
            $latestMeta['wallet_charged_at'] = now()->toIso8601String();
            $message->forceFill(['metadata' => $latestMeta])->save();

            Log::info('Wallet delivery charge applied', [
                'message_id' => $message->id,
                'transaction_id' => $transaction->id,
                'amount' => $unitCost,
                'source' => $source,
                'category' => $category,
                'external_message_id' => $message->external_message_id,
            ]);

            try {
                app(\App\Domains\Alerts\Services\AlertDispatcher::class)
                    ->lowWallet(context: 'template_delivery_charge:'.$source);
            } catch (Throwable) {
                // non-blocking
            }

            return $transaction;
        } catch (Throwable $e) {
            Log::error('Wallet delivery charge failed', [
                'message_id' => $message->id,
                'source' => $source,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataArray(Message $message): array
    {
        $meta = $message->metadata;

        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function isServiceMessage(Message $message): bool
    {
        $direction = $message->direction instanceof MessageDirection
            ? $message->direction
            : MessageDirection::tryFrom((string) $message->direction);

        if ($direction !== MessageDirection::Outbound) {
            return false;
        }

        $type = $message->message_type instanceof MessageType
            ? $message->message_type
            : MessageType::tryFrom((string) $message->message_type);

        if ($type === null || $type === MessageType::Template || $type === MessageType::System) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveSource(array $meta, ?CampaignRecipient $recipient, bool $isService): string
    {
        $explicit = strtolower(trim((string) ($meta['wallet_source'] ?? '')));
        if ($explicit !== '') {
            return $explicit;
        }

        if ($recipient !== null || ! empty($meta['campaign_id'])) {
            return 'campaign';
        }

        return $isService ? 'inbox' : 'inbox';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function descriptionFor(
        string $source,
        string $category,
        ?Campaign $campaign,
        array $meta,
        string $phone,
        bool $isService,
        string $statusKey = 'delivered',
    ): string {
        $suffix = $phone !== '' ? ' · '.$phone : '';
        $serviceWhen = $statusKey === 'sent' ? 'sent' : 'delivered';

        if ($isService || $category === 'SERVICE') {
            return match ($source) {
                'chatbot' => 'Chatbot service conversation ('.$serviceWhen.')'.$suffix,
                'trigger' => 'Trigger service conversation ('.$serviceWhen.')'.$suffix,
                'drip' => 'Drip service conversation ('.$serviceWhen.')'.$suffix,
                default => 'Service conversation ('.$serviceWhen.')'.$suffix,
            };
        }

        return match ($source) {
            'opt_in' => 'Opt-in message (marketing template, delivered)'.$suffix,
            'campaign' => trim(sprintf(
                'Campaign: %s · %s%s',
                $campaign?->name ?? ($meta['campaign_name'] ?? 'Campaign'),
                $category,
                $suffix,
            )),
            'chatbot' => 'Chatbot template ('.$category.')'.$suffix,
            'trigger' => 'Trigger template ('.$category.')'.$suffix,
            'drip' => 'Drip campaign template ('.$category.')'.$suffix,
            'form', 'form_builder' => 'Form builder template ('.$category.')'.$suffix,
            'test' => 'Campaign test message ('.$category.')'.$suffix,
            default => 'WhatsApp template message ('.$category.', delivered)'.$suffix,
        };
    }
}
