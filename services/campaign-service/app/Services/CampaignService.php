<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Repositories\Interfaces\CampaignRecipientRepositoryInterface;
use App\Repositories\Interfaces\CampaignRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepo,
        private readonly CampaignRecipientRepositoryInterface $recipientRepo,
    ) {}

    /**
     * Create a new campaign.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign
    {
        return DB::transaction(function () use ($data): Campaign {
            $campaign = $this->campaignRepo->create([
                'name' => $data['name'],
                'status' => CampaignStatus::Draft,
                'audience_id' => $data['audience_id'] ?? null,
                'whatsapp_line_id' => $data['whatsapp_line_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'template_variables' => $data['template_variables'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'created_by' => $data['created_by'] ?? null,
            ]);

            if ($campaign->scheduled_at) {
                $campaign->update(['status' => CampaignStatus::Scheduled]);
            }

            if (! empty($data['recipients']) && is_array($data['recipients'])) {
                $this->populateRecipients($campaign, $data['recipients']);
            }

            return $campaign->fresh();
        });
    }

    /**
     * Launch/queue campaign execution.
     */
    public function launch(Campaign $campaign): Campaign
    {
        if ($campaign->whatsapp_line_id === null) {
            abort(422, 'Campaign WhatsApp line is required.');
        }

        return app(CampaignSendService::class)->queueCampaign($campaign);
    }

    /**
     * Update campaign.
     *
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data): Campaign {
            $fillable = [];

            foreach (['name', 'timezone'] as $field) {
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
            }

            if ($fillable !== []) {
                $campaign->update($fillable);
            }

            return $campaign->refresh();
        });
    }

    public function schedule(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::Scheduled]);

        return $campaign->refresh();
    }

    public function cancel(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::Cancelled]);

        return $campaign->refresh();
    }

    public function toggle(Campaign $campaign): Campaign
    {
        if ($campaign->isSending()) {
            $campaign->update(['status' => CampaignStatus::Paused]);
        } elseif ($campaign->isPaused()) {
            $campaign->update(['status' => CampaignStatus::Sending]);
            app(CampaignSendService::class)->queueCampaign($campaign);
        }

        return $campaign->refresh();
    }

    public function duplicate(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $new = $this->campaignRepo->create([
                'name' => $campaign->name . ' (Copy)',
                'status' => CampaignStatus::Draft,
                'audience_id' => $campaign->audience_id,
                'whatsapp_line_id' => $campaign->whatsapp_line_id,
                'template_id' => $campaign->template_id,
                'template_variables' => $campaign->template_variables,
                'timezone' => $campaign->timezone,
                'total_recipients' => 0,
            ]);

            return $new;
        });
    }

    /**
     * Populate recipients array into campaign.
     *
     * @param array<int, array{contact_id?: int|null, contact_phone: string, variable_values?: array|null}> $recipients
     */
    public function populateRecipients(Campaign $campaign, array $recipients): int
    {
        $rows = [];
        $now = now();

        foreach ($recipients as $item) {
            $phone = trim((string) ($item['contact_phone'] ?? ''));
            if ($phone === '') {
                continue;
            }

            $rows[] = [
                'campaign_id' => $campaign->id,
                'contact_id' => $item['contact_id'] ?? null,
                'contact_phone' => $phone,
                'variable_values' => isset($item['variable_values']) ? json_encode($item['variable_values']) : null,
                'status' => CampaignRecipientStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $count = $this->recipientRepo->insertBatch($rows);
            $total = CampaignRecipient::query()->where('campaign_id', $campaign->id)->count();
            $campaign->update(['total_recipients' => $total]);

            return $count;
        }

        return 0;
    }
}
