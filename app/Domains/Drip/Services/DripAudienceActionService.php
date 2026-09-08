<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Audience\Models\ContactTag;
use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Enums\CampaignRecipientStatus;
use App\Enums\ChatbotFlowStatAction;
use App\Models\CampaignRecipient;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DripAudienceActionService
{
    public function exportCsv(DripCampaign $campaign): StreamedResponse
    {
        $filename = 'drip-campaign-'.$campaign->id.'-stats-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['contact_phone', 'node_id', 'node_type', 'action', 'created_at']);

            DripCampaignStat::query()
                ->where('drip_campaign_id', $campaign->id)
                ->orderBy('id')
                ->chunkById(200, function ($stats) use ($handle): void {
                    foreach ($stats as $stat) {
                        fputcsv($handle, [
                            $stat->contact_phone,
                            $stat->node_id,
                            $stat->node_type,
                            $stat->action?->value ?? $stat->action,
                            $stat->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  list<string>  $tags
     */
    public function bulkTag(DripCampaign $campaign, array $tags): int
    {
        $phones = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->whereNotNull('contact_phone')
            ->distinct()
            ->pluck('contact_phone');

        $tagged = 0;

        foreach ($phones as $phone) {
            $contact = \App\Models\Contact::query()->where('phone', $phone)->first();

            if ($contact === null) {
                continue;
            }

            foreach ($tags as $tag) {
                $name = trim((string) $tag);

                if ($name === '') {
                    continue;
                }

                ContactTag::query()->firstOrCreate([
                    'contact_id' => $contact->id,
                    'name' => $name,
                ]);
            }

            $tagged++;
        }

        return $tagged;
    }

    public function retryFailed(DripCampaign $campaign): int
    {
        $failedPhones = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->where('action', ChatbotFlowStatAction::Error)
            ->whereNotNull('contact_phone')
            ->distinct()
            ->pluck('contact_phone');

        $retried = 0;

        foreach ($failedPhones as $phone) {
            $recipient = CampaignRecipient::query()
                ->where('contact_phone', $phone)
                ->where('status', CampaignRecipientStatus::Failed)
                ->latest('id')
                ->first();

            if ($recipient === null) {
                continue;
            }

            $recipient->update([
                'status' => CampaignRecipientStatus::Pending,
                'failed_at' => null,
                'failure_reason' => null,
            ]);

            SendCampaignRecipientJob::dispatch((int) $recipient->campaign_id, (int) $recipient->id)
                ->onQueue((string) config('campaigns.queue', 'default'));

            $retried++;
        }

        return $retried;
    }
}
