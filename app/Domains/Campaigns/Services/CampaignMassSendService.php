<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignMassSendService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
        private readonly WhatsappLineRegistryService $registryService,
        private readonly CampaignTemplateParamsResolver $paramsResolver,
    ) {}

    /**
     * Send pending recipients via CAMS SendChatappMassMessage (up to 1,000 / request).
     *
     * @return int Number of recipients accepted by CAMS
     */
    public function dispatchPending(Campaign $campaign): int
    {
        $campaign->loadMissing(['whatsappLine', 'template']);
        $line = $campaign->whatsappLine;
        $template = $campaign->template;

        if (
            $line === null
            || $template === null
            || ! $this->camsClient->isConfigured()
            || blank($line->alibaba_cust_space_id)
        ) {
            return 0;
        }

        $templateCode = CamsTemplateIdentity::code(
            $template->code,
            is_array($template->payload) ? ($template->payload['legacy_template_code'] ?? null) : null,
        );
        if ($templateCode === null) {
            Log::warning('CAMS mass send skipped: template missing valid template_code', [
                'campaign_id' => $campaign->id,
                'template_id' => $template->id,
            ]);

            return 0;
        }

        $batchSize = min(1000, max(1, (int) config('campaigns.mass_batch_size', 1000)));
        $accepted = 0;
        $from = $this->formatPhone((string) $line->phone);
        $language = CamsTemplateIdentity::language($template->language);

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->orderBy('id')
            ->chunkById($batchSize, function ($recipients) use ($campaign, $line, $template, $templateCode, $from, $language, &$accepted): void {
                $senderList = [];
                $batchRecipients = [];

                foreach ($recipients as $recipient) {
                    $to = $this->formatPhone((string) $recipient->contact_phone);
                    if ($to === '') {
                        continue;
                    }

                    $senderList[] = [
                        'To' => $to,
                        'TemplateParams' => json_encode(
                            $this->paramsResolver->forRecipient($campaign, $recipient),
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                        ),
                    ];
                    $batchRecipients[] = $recipient;
                }

                if ($senderList === []) {
                    return;
                }

                try {
                    $response = $this->camsClient->sendChatappMassMessage([
                        'From' => $from,
                        'TemplateCode' => $templateCode,
                        'Language' => $language,
                        'CustSpaceId' => $line->alibaba_cust_space_id,
                        'SenderList' => $senderList,
                    ]);
                } catch (Throwable $exception) {
                    Log::warning('CAMS mass send request failed', [
                        'campaign_id' => $campaign->id,
                        'error' => $exception->getMessage(),
                    ]);

                    return;
                }

                $body = $response->json();
                if (! is_array($body)) {
                    $body = [];
                }

                if (! $this->isCamsSuccess($response->successful(), $body)) {
                    Log::warning('CAMS mass send rejected', [
                        'campaign_id' => $campaign->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return;
                }

                $groupId = (string) Arr::get($body, 'GroupId', Arr::get($body, 'groupId', Arr::get($body, 'TaskId', '')));
                $now = now();

                foreach ($batchRecipients as $recipient) {
                    $messageId = $groupId !== '' ? $groupId : ('mass_'.$campaign->id.'_'.$recipient->id);
                    $values = (array) ($recipient->variable_values ?? []);
                    $values['cams_group_id'] = $groupId !== '' ? $groupId : null;

                    $recipient->update([
                        'status' => CampaignRecipientStatus::Sent,
                        'sent_at' => $now,
                        'message_id' => $messageId,
                        'failure_reason' => null,
                        'failed_at' => null,
                        'variable_values' => $values,
                    ]);

                    $accepted++;
                }

                $campaign->increment('total_delivered', count($batchRecipients));

                if ($groupId !== '' && tenancy()->initialized) {
                    $tenantId = tenant('id');
                    if (is_string($tenantId) && $tenantId !== '') {
                        $this->registryService->indexMessage($tenantId, $groupId);
                    }
                }
            });

        return $accepted;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isCamsSuccess(bool $httpOk, array $body): bool
    {
        if (! $httpOk) {
            return false;
        }

        $code = $body['Code'] ?? $body['code'] ?? null;
        if ($code === null || $code === '') {
            return true;
        }

        return in_array(strtoupper((string) $code), ['OK', '200', 'SUCCESS'], true);
    }

    private function formatPhone(string $phone): string
    {
        // Alibaba CAMS requires From/To as digits only (InvalidParameter.FromOnlyNumeric).
        return PhoneNormalizer::normalize($phone)
            ?? (preg_replace('/\D+/', '', trim($phone)) ?? '');
    }
}
