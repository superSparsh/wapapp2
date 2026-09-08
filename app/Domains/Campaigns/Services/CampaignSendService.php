<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Services\OptInTemplateService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignSendService
{
    public function __construct(
        private readonly InboxConversationService $conversationService,
        private readonly InboxOutboundService $outboundService,
        private readonly OptInTemplateService $optInTemplateService,
        private readonly CampaignWebhookService $webhookService,
        private readonly CampaignMassSendService $massSendService,
        private readonly CampaignTemplateParamsResolver $paramsResolver,
    ) {}

    public function queueCampaign(Campaign $campaign): Campaign
    {
        $campaign = $campaign->fresh(['whatsappLine', 'template']);

        abort_if($campaign->whatsappLine === null, 422, 'Campaign WhatsApp line is required.');
        abort_if($campaign->template === null, 422, 'Campaign template is required.');

        if ($campaign->total_recipients === 0 && $campaign->audience_id) {
            app(CampaignService::class)->populateRecipients($campaign);
            $campaign->refresh();
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

        // High volume → CAMS mass API; low volume → simple SendChatappMessage via jobs.
        if ($pendingCount >= $massThreshold) {
            $this->massSendService->dispatchPending($campaign);
        }

        $batchSize = (int) config('campaigns.dispatch_batch_size', 100);

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->orderBy('id')
            ->chunkById($batchSize, function ($recipients) use ($campaign): void {
                foreach ($recipients as $recipient) {
                    \App\Domains\Campaigns\Jobs\SendCampaignRecipientJob::dispatch(
                        (int) $campaign->id,
                        (int) $recipient->id,
                    )->onQueue((string) config('campaigns.queue', 'default'));
                }
            });

        $this->refreshCampaignCompletion($campaign);

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

        $line = $campaign->whatsappLine;
        $template = $campaign->template;

        if ($line === null || $template === null) {
            $this->markFailed($recipient, 'Missing line or template.');

            return;
        }

        try {
            $conversation = $this->conversationService->findOrCreateConversation(
                line: $line,
                contactPhone: $recipient->contact_phone,
            );

            $params = $this->paramsResolver->forRecipient($campaign, $recipient);

            $templateCode = CamsTemplateIdentity::code(
                $template->code,
                is_array($template->payload) ? ($template->payload['legacy_template_code'] ?? null) : null,
            );

            if ($templateCode === null) {
                $this->markFailed($recipient, 'Template is missing a valid WhatsApp template_code.');
                $campaign->increment('total_failed');

                return;
            }

            // Send sync so Alibaba MessageId is available before we store campaign_recipients.message_id.
            // Async dispatch left message_id as the local messages.id, so status webhooks never matched.
            $message = $this->outboundService->sendTemplate(
                conversation: $conversation,
                templateCode: $templateCode,
                templateParams: $params,
                language: CamsTemplateIdentity::language($template->language),
                sendImmediately: true,
            );

            $message->refresh();

            if ($message->status === MessageStatus::Failed) {
                $reason = (string) ($message->failed_reason ?: 'Provider rejected the message.');
                $this->markFailed($recipient, $reason);
                $campaign->increment('total_failed');

                $this->webhookService->dispatch($campaign, 'failed', [
                    'recipient_id' => $recipient->id,
                    'contact_phone' => $recipient->contact_phone,
                    'reason' => $reason,
                ]);

                return;
            }

            $providerMessageId = trim((string) ($message->external_message_id ?? ''));
            $storedMessageId = $providerMessageId !== '' ? $providerMessageId : (string) $message->id;

            if ($providerMessageId === '') {
                Log::warning('Campaign recipient sent without provider MessageId; status webhooks may not match', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'local_message_id' => $message->id,
                ]);
            }

            $recipient->update([
                'status' => CampaignRecipientStatus::Sent,
                'sent_at' => now(),
                'message_id' => $storedMessageId,
                'failure_reason' => null,
                'failed_at' => null,
            ]);

            $campaign->increment('total_delivered');

            $this->webhookService->dispatch($campaign, 'sent', [
                'recipient_id' => $recipient->id,
                'contact_phone' => $recipient->contact_phone,
                'message_id' => $storedMessageId,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Campaign recipient send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);

            if (str_contains($exception->getMessage(), '131049')) {
                $this->optInTemplateService->ensureExists();
            }

            $this->markFailed($recipient, $exception->getMessage());
            $campaign->increment('total_failed');

            $this->webhookService->dispatch($campaign, 'failed', [
                'recipient_id' => $recipient->id,
                'contact_phone' => $recipient->contact_phone,
                'reason' => $exception->getMessage(),
            ]);
        }

        $this->refreshCampaignCompletion($campaign);
    }

    public function refreshCampaignCompletion(Campaign $campaign): void
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

    private function markFailed(CampaignRecipient $recipient, string $reason): void
    {
        $recipient->update([
            'status' => CampaignRecipientStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => mb_substr($reason, 0, 255),
        ]);
    }
}
