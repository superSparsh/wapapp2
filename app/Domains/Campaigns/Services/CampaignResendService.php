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
use App\Models\Template;
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
                app(CampaignOciWorkerLifecycle::class)->onCampaignStarted(
                    $campaign->fresh() ?? $campaign,
                    is_string(tenant('id')) ? tenant('id') : null,
                );
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

        $sendNow = $sendOption === 'now';
        $templateId = $this->resolveTemplateIdForResend($source);

        // Send-now needs a live template row. Schedule can open the wizard without one
        // so the user can pick a replacement on the Template step.
        abort_if(
            $sendNow && $templateId === null,
            422,
            'The original campaign template no longer exists. Choose “Schedule for Later” and pick a template, or restore the template first.',
        );

        $result = DB::transaction(function () use ($source, $listName, $campaignName, $templateId): array {
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
                'template_id' => $templateId,
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
     * Resolve a template id that still exists for the resend campaign.
     * Source campaigns can keep a stale template_id after deletes/imports.
     */
    private function resolveTemplateIdForResend(Campaign $source): ?int
    {
        $sourceTemplateId = (int) ($source->template_id ?? 0);
        if ($sourceTemplateId > 0 && Template::query()->whereKey($sourceTemplateId)->exists()) {
            return $sourceTemplateId;
        }

        $vars = is_array($source->template_variables) ? $source->template_variables : [];
        $codes = array_values(array_filter([
            trim((string) ($vars['template_code'] ?? '')),
            trim((string) ($vars['legacy_template_code'] ?? '')),
        ], static fn (string $code): bool => $code !== ''));

        foreach ($codes as $code) {
            $match = Template::query()
                ->where('code', $code)
                ->when(
                    $source->whatsapp_line_id !== null,
                    fn ($q) => $q->where(function ($inner) use ($source): void {
                        $inner->where('whatsapp_line_id', $source->whatsapp_line_id)
                            ->orWhereNull('whatsapp_line_id');
                    }),
                )
                ->orderByRaw('CASE WHEN whatsapp_line_id = ? THEN 0 ELSE 1 END', [(int) $source->whatsapp_line_id])
                ->orderByDesc('id')
                ->first();

            if ($match instanceof Template) {
                return (int) $match->id;
            }

            // Some imports store CAMS code only inside payload.
            $byPayload = Template::query()
                ->where(function ($q) use ($code): void {
                    $q->where('payload->legacy_template_code', $code)
                        ->orWhere('payload->template_code', $code);
                })
                ->orderByDesc('id')
                ->first();

            if ($byPayload instanceof Template) {
                return (int) $byPayload->id;
            }
        }

        return null;
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
