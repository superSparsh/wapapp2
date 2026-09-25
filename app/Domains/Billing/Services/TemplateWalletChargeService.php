<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Campaigns\Services\CampaignCostCalculator;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Enums\WalletTransactionType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Debit wallet once per delivered billable WhatsApp message.
 *
 * - Templates: always (campaign / inbox / chatbot / …), category from metadata.
 * - Service (session) messages: from META_SERVICE_BILLING_STARTS_AT (default 2026-10-01).
 * - Utility 24h same-conversation skip only applies before that date.
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
        if (! in_array($deliveryStatus, ['Delivered', 'Read'], true)) {
            return null;
        }

        $meta = is_array($message->metadata) ? $message->metadata : [];
        if (! empty($meta['wallet_charged'])) {
            return null;
        }

        // Explicit opt-out for platform alerts / non-tenant sends.
        if (array_key_exists('billable', $meta) && ! $meta['billable']) {
            return null;
        }

        $isTemplate = $message->message_type === MessageType::Template;
        $isService = $this->isServiceMessage($message);

        if (! $isTemplate && ! $isService) {
            return null;
        }

        $message->loadMissing('conversation');

        $source = $this->resolveSource($meta, $recipient, $isService);
        $category = $isTemplate
            ? strtoupper((string) ($meta['template_category'] ?? 'MARKETING'))
            : 'SERVICE';

        // Pre–Oct 1 Meta parity: second utility in same conversation within 24h is free.
        if (
            $isTemplate
            && $category === 'UTILITY'
            && ! $this->serviceBillingStarted()
            && $this->hasOpenUtilityWindow($message, $meta)
        ) {
            Log::info('Wallet charge skipped: utility 24h window still open', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
            ]);

            return null;
        }

        $unitCost = $this->costCalculator->unitCostForCategory($category);
        if ($unitCost <= 0) {
            Log::warning('Wallet charge skipped: zero unit cost', [
                'message_id' => $message->id,
                'category' => $category,
                'source' => $source,
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

        $description = $this->descriptionFor($source, $category, $campaign, $meta, $phone, $isService);

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

            $meta['wallet_charged'] = true;
            $meta['wallet_transaction_id'] = $transaction->id;
            $meta['wallet_charged_at'] = now()->toIso8601String();
            $message->forceFill(['metadata' => $meta])->save();

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

    public function serviceBillingStarted(?Carbon $at = null): bool
    {
        $raw = trim((string) config('campaigns.meta_service_billing_starts_at', '2026-10-01'));
        if ($raw === '') {
            return true;
        }

        try {
            $start = Carbon::parse($raw, 'Asia/Kolkata')->startOfDay();
        } catch (Throwable) {
            $start = Carbon::parse('2026-10-01', 'Asia/Kolkata')->startOfDay();
        }

        $point = ($at ?? now())->copy()->timezone('Asia/Kolkata');

        return $point->greaterThanOrEqualTo($start);
    }

    private function isServiceMessage(Message $message): bool
    {
        if (! $this->serviceBillingStarted()) {
            return false;
        }

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
    ): string {
        $suffix = $phone !== '' ? ' · '.$phone : '';

        if ($isService || $category === 'SERVICE') {
            return match ($source) {
                'chatbot' => 'Chatbot service message (delivered)'.$suffix,
                'trigger' => 'Trigger service message (delivered)'.$suffix,
                'drip' => 'Drip service message (delivered)'.$suffix,
                default => 'Service message (session, delivered)'.$suffix,
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

    /**
     * @param  array<string, mixed>  $meta
     */
    private function hasOpenUtilityWindow(Message $message, array $meta): bool
    {
        $conversationId = (int) ($message->conversation_id ?: ($meta['conversation_id'] ?? 0));
        if ($conversationId <= 0) {
            return false;
        }

        return WalletTransaction::query()
            ->where('type', WalletTransactionType::Debit)
            ->where('metadata->template_category', 'UTILITY')
            ->where('metadata->conversation_id', $conversationId)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
    }
}
