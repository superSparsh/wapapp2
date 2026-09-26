<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Services\OptInMessageService;
use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\ContactOptInStatus;
use App\Enums\RecordStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MailList;
use App\Support\OciWorkload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignResendService
{
    public function __construct(
        private readonly CampaignService $campaignService,
        private readonly CampaignSendService $sendService,
        private readonly OptInMessageService $optInMessageService,
    ) {}

    /**
     * Legacy parity: requeue failed recipients on the same campaign.
     */
    public function resendFailed(Campaign $campaign): int
    {
        $count = 0;

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Failed)
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use ($campaign, &$count): void {
                foreach ($recipients as $recipient) {
                    $recipient->update([
                        'status' => CampaignRecipientStatus::Pending,
                        'failed_at' => null,
                        'failure_reason' => null,
                        'sent_at' => null,
                        'message_id' => null,
                    ]);

                    SendCampaignRecipientJob::dispatch((int) $campaign->id, (int) $recipient->id)
                        ->onQueue(OciWorkload::campaignQueue());

                    $count++;
                }
            });

        if ($count > 0) {
            $failedLeft = max(0, (int) $campaign->total_failed - $count);
            $campaign->update([
                'status' => CampaignStatus::Sending,
                'completed_at' => null,
                'total_failed' => $failedLeft,
            ]);

            try {
                app(CampaignOciWorkerLifecycle::class)->onCampaignStarted($campaign->fresh() ?? $campaign);
            } catch (Throwable $e) {
                Log::warning('OCI campaign worker provision on resend failed', ['error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * Legacy parity: create a new list + campaign from failed recipients.
     *
     * @return array{list: MailList, campaign: Campaign, imported: int, launched: bool}
     */
    public function createCampaignFromFailed(
        Campaign $source,
        string $listName,
        string $campaignName,
        string $sendOption = 'now',
    ): array {
        $listName = trim($listName);
        $campaignName = trim($campaignName);
        abort_if($listName === '', 422, 'List name is required.');
        abort_if($campaignName === '', 422, 'Campaign name is required.');
        abort_if($source->whatsapp_line_id === null, 422, 'Source campaign has no WhatsApp number.');
        abort_if($source->template_id === null, 422, 'Source campaign has no template.');

        $sendNow = $sendOption === 'now';

        $result = DB::transaction(function () use ($source, $listName, $campaignName): array {
            $list = MailList::query()->create([
                'name' => $listName,
                'status' => RecordStatus::Active,
                'description' => 'Created from failed recipients of campaign: '.$source->name,
            ]);

            $imported = 0;
            $seen = [];

            CampaignRecipient::query()
                ->where('campaign_id', $source->id)
                ->where('status', CampaignRecipientStatus::Failed)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($list, &$imported, &$seen): void {
                    foreach ($rows as $row) {
                        $phone = trim((string) $row->contact_phone);
                        if ($phone === '' || isset($seen[$phone])) {
                            continue;
                        }
                        $seen[$phone] = true;

                        $sourceContact = $row->contact_id
                            ? Contact::query()->find($row->contact_id)
                            : null;

                        Contact::query()->firstOrCreate(
                            [
                                'mail_list_id' => $list->id,
                                'phone' => $phone,
                            ],
                            [
                                'name' => $sourceContact?->name,
                                'email' => $sourceContact?->email,
                                'country_code' => $sourceContact?->country_code,
                                'status' => ContactStatus::Subscribed,
                                'opt_in_status' => ContactOptInStatus::OptedIn,
                                'opted_in_at' => now(),
                                'source' => 'campaign_failed_resend',
                            ]
                        );
                        $imported++;
                    }
                });

            abort_if($imported === 0, 422, 'No failed contacts found to resend.');

            $campaign = Campaign::query()->create([
                'name' => $campaignName,
                'status' => CampaignStatus::Draft,
                'audience_id' => $list->id,
                'whatsapp_line_id' => $source->whatsapp_line_id,
                'template_id' => $source->template_id,
                'template_variables' => $source->template_variables,
                'timezone' => $source->timezone,
            ]);

            $this->campaignService->populateRecipients($campaign);

            return [
                'list' => $list,
                'campaign' => $campaign->fresh() ?? $campaign,
                'imported' => $imported,
            ];
        });

        $launched = false;
        if ($sendNow) {
            $this->sendService->queueCampaign($result['campaign']);
            $launched = true;
        }

        return [
            'list' => $result['list'],
            'campaign' => $result['campaign']->fresh() ?? $result['campaign'],
            'imported' => $result['imported'],
            'launched' => $launched,
        ];
    }

    /**
     * Legacy parity: resend opt-in template to failed campaign recipients.
     *
     * @return array{attempted: int, sent: int, skipped: int}
     */
    public function resendOptInToFailed(Campaign $campaign): array
    {
        $line = $campaign->whatsappLine;
        abort_if($line === null, 422, 'Campaign WhatsApp Phone Number is required.');

        $attempted = 0;
        $sent = 0;
        $skipped = 0;
        $seenPhones = [];

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Failed)
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use ($line, &$attempted, &$sent, &$skipped, &$seenPhones): void {
                foreach ($recipients as $recipient) {
                    $phone = trim((string) $recipient->contact_phone);
                    if ($phone === '' || isset($seenPhones[$phone])) {
                        $skipped++;

                        continue;
                    }
                    $seenPhones[$phone] = true;
                    $attempted++;

                    $contact = $recipient->contact_id
                        ? Contact::query()->find($recipient->contact_id)
                        : null;

                    if ($contact === null) {
                        $contact = Contact::query()->where('phone', $phone)->orderBy('id')->first();
                    }

                    if ($contact === null) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $ok = $this->optInMessageService->sendOptInToContact($contact, $line, force: true);
                        if ($ok) {
                            $sent++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $e) {
                        $skipped++;
                        Log::warning('Campaign resend opt-in failed', [
                            'campaign_id' => $recipient->campaign_id,
                            'recipient_id' => $recipient->id,
                            'contact_id' => $contact->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'skipped' => $skipped,
        ];
    }
}
