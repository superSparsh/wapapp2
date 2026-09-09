<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Campaigns\Services\CampaignSendService;
use App\Domains\Audience\Enums\ContactStatus;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\ContactOptInStatus;
use App\Enums\RecordStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MailList;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    /**
     * Create a new campaign with all wizard data.
     *
     * @param  array{name: string, audience_id?: int|null, whatsapp_line_id?: int|null, template_id?: int|null, template_variables?: array|null, scheduled_at?: string|null}  $data
     */
    public function create(array $data): Campaign
    {
        return DB::transaction(function () use ($data): Campaign {
            $campaign = Campaign::query()->create([
                'name' => $data['name'],
                'status' => CampaignStatus::Draft,
                'audience_id' => $data['audience_id'] ?? null,
                'whatsapp_line_id' => $data['whatsapp_line_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'template_variables' => $data['template_variables'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            // If scheduled_at is set, transition to scheduled
            if ($campaign->scheduled_at) {
                $campaign->update(['status' => CampaignStatus::Scheduled]);
            }

            // Populate recipients from audience
            if ($campaign->audience_id) {
                $this->populateRecipients($campaign);
            }

            $campaign = $campaign->fresh();

            return $campaign;
        });
    }

    public function launch(Campaign $campaign): Campaign
    {
        abort_if($campaign->whatsapp_line_id === null, 422, 'Campaign WhatsApp line is required.');
        abort_if($campaign->template_id === null, 422, 'Campaign template is required.');

        return app(CampaignSendService::class)->queueCampaign($campaign);
    }

    /**
     * Update a draft/scheduled campaign.
     *
     * @param  array{name?: string, audience_id?: int|null, whatsapp_line_id?: int|null, template_id?: int|null, template_variables?: array|null, scheduled_at?: string|null}  $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data): Campaign {
            $previousAudienceId = $campaign->audience_id;
            $previousRecipientCount = (int) $campaign->total_recipients;
            $fillable = [];

            foreach (['name'] as $field) {
                if (isset($data[$field])) {
                    $fillable[$field] = $data[$field];
                }
            }

            foreach (['audience_id', 'whatsapp_line_id', 'template_id'] as $fk) {
                if (array_key_exists($fk, $data)) {
                    $fillable[$fk] = $data[$fk];
                }
            }

            if (array_key_exists('template_variables', $data)) {
                $fillable['template_variables'] = $data['template_variables'];
            }

            if (array_key_exists('scheduled_at', $data)) {
                $fillable['scheduled_at'] = $data['scheduled_at'] ?: null;
                if (! empty($fillable['scheduled_at']) && $campaign->status === CampaignStatus::Draft) {
                    $fillable['status'] = CampaignStatus::Scheduled;
                }
            }

            if ($fillable !== []) {
                $campaign->update($fillable);
            }

            $campaign = $campaign->refresh();

            $audienceId = $campaign->audience_id;
            if ($audienceId && (
                (int) $previousAudienceId !== (int) $audienceId
                || $previousRecipientCount === 0
            )) {
                $this->populateRecipients($campaign);
                $campaign = $campaign->refresh();
            }

            return $campaign;
        });
    }

    /**
     * Transition campaign to scheduled status.
     */
    public function schedule(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::Scheduled]);

        return $campaign->refresh();
    }

    /**
     * Cancel a scheduled/sending campaign.
     */
    public function cancel(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::Cancelled]);

        return $campaign->refresh();
    }

    /**
     * Toggle pause/resume for an active campaign.
     */
    public function toggle(Campaign $campaign): Campaign
    {
        $newStatus = $campaign->isSending()
            ? CampaignStatus::Paused
            : CampaignStatus::Sending;

        $campaign->update(['status' => $newStatus]);

        return $campaign->refresh();
    }

    /**
     * Delete campaign with cascade (transaction).
     */
    public function delete(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $campaign->recipients()->delete();
            $campaign->delete();
        });
    }

    /**
     * Duplicate a campaign as draft with '(Copy)' suffix.
     */
    public function duplicate(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $clone = $campaign->replicate(['uuid']);
            $clone->name = $campaign->name . ' (Copy)';
            $clone->status = CampaignStatus::Draft;
            $clone->started_at = null;
            $clone->completed_at = null;
            $clone->total_recipients = 0;
            $clone->total_delivered = 0;
            $clone->total_failed = 0;
            $clone->total_read = 0;
            $clone->total_unsubscribed = 0;
            $clone->save();

            return $clone;
        });
    }

    /**
     * Populate recipients from the campaign's audience (MailList contacts).
     * Uses chunked insert for memory efficiency with large audiences.
     */
    public function populateRecipients(Campaign $campaign): int
    {
        if (! $campaign->audience_id) {
            return 0;
        }

        // Clear existing recipients
        $campaign->recipients()->delete();

        $count = 0;
        $chunkSize = 500;

        Contact::query()
            ->where('mail_list_id', $campaign->audience_id)
            ->where('status', ContactStatus::Subscribed)
            ->select(['id', 'phone'])
            ->chunk($chunkSize, function ($contacts) use ($campaign, &$count): void {
                $rows = $contacts->map(fn (Contact $contact) => [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'contact_phone' => $contact->phone,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                CampaignRecipient::query()->insert($rows);
                $count += count($rows);
            });

        // Update denormalized counter
        $campaign->update(['total_recipients' => $count]);

        return $count;
    }

    /**
     * Create a new mail list from successfully delivered campaign recipients (legacy parity).
     *
     * @return array{list: MailList, imported: int}
     */
    public function createDeliveredMailList(Campaign $campaign, string $listName): array
    {
        $listName = trim($listName);
        abort_if($listName === '', 422, 'List name is required.');

        return DB::transaction(function () use ($campaign, $listName): array {
            $list = MailList::query()->create([
                'name' => $listName,
                'status' => RecordStatus::Active,
                'description' => 'Created from delivered recipients of campaign: '.$campaign->name,
            ]);

            $imported = 0;
            $seen = [];

            CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('status', [
                    CampaignRecipientStatus::Delivered,
                    CampaignRecipientStatus::Read,
                    CampaignRecipientStatus::Response,
                ])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($list, &$imported, &$seen): void {
                    foreach ($rows as $row) {
                        $phone = trim((string) $row->contact_phone);
                        if ($phone === '' || isset($seen[$phone])) {
                            continue;
                        }
                        $seen[$phone] = true;

                        $source = $row->contact_id
                            ? Contact::query()->find($row->contact_id)
                            : null;

                        Contact::query()->firstOrCreate(
                            [
                                'mail_list_id' => $list->id,
                                'phone' => $phone,
                            ],
                            [
                                'name' => $source?->name,
                                'email' => $source?->email,
                                'country_code' => $source?->country_code,
                                'status' => ContactStatus::Subscribed,
                                'opt_in_status' => ContactOptInStatus::OptedIn,
                                'opted_in_at' => now(),
                                'source' => 'campaign_delivered',
                            ]
                        );
                        $imported++;
                    }
                });

            return ['list' => $list, 'imported' => $imported];
        });
    }
}
