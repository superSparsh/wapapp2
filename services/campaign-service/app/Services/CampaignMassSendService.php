<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\CampaignCamsContext;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignMassSendService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * @return int Recipients accepted by CAMS mass API
     */
    public function dispatchPending(Campaign $campaign): int
    {
        if (! $this->camsClient->isConfigured()) {
            return 0;
        }

        $ctx = CampaignCamsContext::fromCampaign($campaign);
        if ($ctx === null) {
            Log::info('Skipping mass send: missing CAMS context on campaign', [
                'campaign_id' => $campaign->id,
            ]);

            return 0;
        }

        $batchSize = min(1000, max(1, (int) config('campaigns.mass_batch_size', 1000)));
        $accepted = 0;

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->orderBy('id')
            ->chunkById($batchSize, function ($recipients) use ($campaign, $ctx, &$accepted): void {
                $senderList = [];
                $batch = [];

                foreach ($recipients as $recipient) {
                    $to = $this->formatPhone((string) $recipient->contact_phone);
                    if ($to === '') {
                        continue;
                    }

                    $senderList[] = [
                        'To' => $to,
                        'TemplateParams' => json_encode(
                            CampaignCamsContext::templateParams(
                                (array) ($campaign->template_variables ?? []),
                                (array) ($recipient->variable_values ?? []),
                            ),
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                        ),
                    ];
                    $batch[] = $recipient;
                }

                if ($senderList === []) {
                    return;
                }

                try {
                    $response = $this->camsClient->sendChatappMassMessage([
                        'From' => $ctx['line_phone'],
                        'TemplateCode' => $ctx['template_code'],
                        'Language' => $ctx['language'],
                        'CustSpaceId' => $ctx['cust_space_id'],
                        'SenderList' => $senderList,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('CAMS mass send failed', [
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);

                    return;
                }

                $body = is_array($response->json()) ? $response->json() : [];
                if (! $this->isOk($response->successful(), $body)) {
                    Log::warning('CAMS mass send rejected', [
                        'campaign_id' => $campaign->id,
                        'body' => $response->body(),
                    ]);

                    return;
                }

                $groupId = (string) Arr::get($body, 'GroupId', Arr::get($body, 'groupId', Arr::get($body, 'TaskId', '')));
                $now = now();

                foreach ($batch as $recipient) {
                    $values = (array) ($recipient->variable_values ?? []);
                    $values['cams_group_id'] = $groupId !== '' ? $groupId : null;

                    $recipient->update([
                        'status' => CampaignRecipientStatus::Sent,
                        'sent_at' => $now,
                        'message_id' => $groupId !== '' ? $groupId : ('mass_'.$campaign->id.'_'.$recipient->id),
                        'failure_reason' => null,
                        'failed_at' => null,
                        'variable_values' => $values,
                    ]);
                    $accepted++;
                }

                $campaign->increment('total_delivered', count($batch));
            });

        return $accepted;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isOk(bool $httpOk, array $body): bool
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
        $normalized = PhoneNormalizer::normalize($phone) ?? trim($phone);
        if ($normalized === '') {
            return '';
        }

        return str_starts_with($normalized, '+') ? $normalized : '+'.$normalized;
    }
}
