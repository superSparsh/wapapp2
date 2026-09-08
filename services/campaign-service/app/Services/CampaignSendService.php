<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\OutboundTemplateSenderInterface;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignRecipientJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\CampaignCamsContext;
use App\Support\PhoneNormalizer;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignSendService
{
    public function __construct(
        private readonly OutboundTemplateSenderInterface $templateSender,
        private readonly CampaignWebhookService $webhookService,
        private readonly CampaignMassSendService $massSendService,
        private readonly AlibabaCamsClient $camsClient,
        private readonly TenantContext $tenantContext,
    ) {}

    public function queueCampaign(Campaign $campaign): Campaign
    {
        $campaign->refresh();

        if ($campaign->whatsapp_line_id === null) {
            abort(422, 'Campaign WhatsApp line is required.');
        }

        $campaign->update([
            'status' => CampaignStatus::Sending,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        $pendingCount = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->count();

        $massThreshold = max(1, (int) config('campaigns.mass_threshold', 50));

        // High volume → mass API; low volume → simple API jobs only.
        if ($pendingCount >= $massThreshold) {
            $this->massSendService->dispatchPending($campaign);
        }

        $batchSize = (int) config('campaigns.dispatch_batch_size', 100);
        $tenantId = (string) ($campaign->tenant_id ?? $this->tenantContext->getTenantId() ?? '');

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->orderBy('id')
            ->chunkById($batchSize, function ($recipients) use ($campaign, $tenantId): void {
                foreach ($recipients as $recipient) {
                    SendCampaignRecipientJob::dispatch(
                        (int) $campaign->id,
                        (int) $recipient->id,
                        $tenantId,
                    )->onQueue((string) config('campaigns.queue', 'default'));
                }
            });

        $this->checkCompletion($campaign);

        return $campaign->refresh();
    }

    public function sendRecipient(Campaign $campaign, CampaignRecipient $recipient): void
    {
        if ($campaign->isPaused() || $campaign->isCancelled()) {
            return;
        }

        if ($recipient->status !== CampaignRecipientStatus::Pending) {
            return;
        }

        $lineId = $campaign->whatsapp_line_id;
        if ($lineId === null) {
            $this->markFailed($recipient, 'Missing WhatsApp line.');

            return;
        }

        try {
            $ctx = CampaignCamsContext::fromCampaign($campaign);
            $templateCode = (string) ($ctx['template_code'] ?? $campaign->template_variables['template_code'] ?? 'template_'.$campaign->template_id);
            $templateParams = CampaignCamsContext::templateParams(
                (array) ($campaign->template_variables ?? []),
                (array) ($recipient->variable_values ?? []),
            );
            $language = (string) ($ctx['language'] ?? config('whatsapp.alibaba.default_language', 'en_GB'));

            if ($this->camsClient->isConfigured() && $ctx !== null) {
                $result = $this->sendSimpleViaCams($ctx, $recipient->contact_phone, $templateCode, $templateParams, $language);
            } else {
                $result = $this->templateSender->sendTemplate(
                    whatsappLineId: (int) $lineId,
                    contactPhone: $recipient->contact_phone,
                    templateCode: $templateCode,
                    templateParams: $templateParams,
                    language: $language,
                );
            }

            if ($result['success']) {
                $recipient->update([
                    'status' => CampaignRecipientStatus::Sent,
                    'sent_at' => now(),
                    'message_id' => $result['message_id'],
                    'failure_reason' => null,
                    'failed_at' => null,
                ]);

                $campaign->increment('total_delivered');

                $this->webhookService->dispatch($campaign, 'sent', [
                    'recipient_id' => $recipient->id,
                    'contact_phone' => $recipient->contact_phone,
                    'message_id' => $result['message_id'],
                ]);
            } else {
                $this->markFailed($recipient, (string) ($result['error'] ?? 'Send failed.'));
            }
        } catch (Throwable $e) {
            Log::warning('Campaign recipient send exception', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($recipient, $e->getMessage());
        }

        $this->checkCompletion($campaign);
    }

    /**
     * @param  array{template_code: string, language: string, line_phone: string, cust_space_id: string}  $ctx
     * @param  array<string, string>  $templateParams
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    private function sendSimpleViaCams(
        array $ctx,
        string $contactPhone,
        string $templateCode,
        array $templateParams,
        string $language,
    ): array {
        $to = PhoneNormalizer::normalize($contactPhone) ?? $contactPhone;
        $to = str_starts_with($to, '+') ? $to : '+'.$to;

        $response = $this->camsClient->sendChatappMessage([
            'From' => $ctx['line_phone'],
            'To' => $to,
            'Type' => 'template',
            'TemplateCode' => $templateCode !== '' ? $templateCode : $ctx['template_code'],
            'Language' => $language ?: $ctx['language'],
            'CustSpaceId' => $ctx['cust_space_id'],
            'TemplateParams' => json_encode($templateParams, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        $body = is_array($response->json()) ? $response->json() : [];
        $code = $body['Code'] ?? $body['code'] ?? null;
        $ok = $response->successful()
            && ($code === null || $code === '' || in_array(strtoupper((string) $code), ['OK', '200', 'SUCCESS'], true));

        if (! $ok) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $response->body(),
            ];
        }

        $messageId = (string) Arr::get($body, 'MessageId', Arr::get($body, 'messageId', ''));

        return [
            'success' => true,
            'message_id' => $messageId !== '' ? $messageId : null,
            'error' => null,
        ];
    }

    private function markFailed(CampaignRecipient $recipient, string $reason): void
    {
        $recipient->update([
            'status' => CampaignRecipientStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => mb_substr($reason, 0, 255),
        ]);

        $recipient->campaign?->increment('total_failed');

        if ($recipient->campaign) {
            $this->webhookService->dispatch($recipient->campaign, 'failed', [
                'recipient_id' => $recipient->id,
                'contact_phone' => $recipient->contact_phone,
                'reason' => $reason,
            ]);
        }
    }

    private function checkCompletion(Campaign $campaign): void
    {
        $pending = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->exists();

        if (! $pending && $campaign->isSending()) {
            $campaign->update([
                'status' => CampaignStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }
}
