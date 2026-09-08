<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Drip\Support\DripFlowCacheManager;
use App\Domains\Drip\Support\DripTriggerCatalog;
use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use Illuminate\Support\Facades\DB;

class DripCampaignService
{
    public function __construct(
        private readonly DripFlowCacheManager $cacheManager,
    ) {}

    /**
     * @param  array{name: string, audience_id?: int|null, timezone?: string|null, start_date?: string|null, end_date?: string|null, trigger_type?: string|null, trigger_options?: array<string, mixed>|null}  $data
     */
    public function create(array $data): DripCampaign
    {
        $triggerType = DripTriggerCatalog::normalizeType($data['trigger_type'] ?? 'welcome-new-subscriber');

        return DB::transaction(function () use ($data, $triggerType): DripCampaign {
            return DripCampaign::query()->create([
                'name' => $data['name'],
                'status' => ChatbotFlowStatus::Draft,
                'audience_id' => $data['audience_id'] ?? null,
                'timezone' => $data['timezone'] ?? config('chatbot.drip.default_timezone', 'Asia/Kolkata'),
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'trigger_type' => $triggerType,
                'trigger_options' => DripTriggerCatalog::sanitizeOptions(
                    $triggerType,
                    (array) ($data['trigger_options'] ?? []),
                ),
            ]);
        });
    }

    /**
     * @param  array{name?: string, audience_id?: int|null, timezone?: string|null, start_date?: string|null, end_date?: string|null, trigger_type?: string|null, trigger_options?: array<string, mixed>|null}  $data
     */
    public function updateSettings(DripCampaign $campaign, array $data): DripCampaign
    {
        return DB::transaction(function () use ($campaign, $data): DripCampaign {
            $fillable = [];

            foreach (['name', 'timezone'] as $field) {
                if (isset($data[$field])) {
                    $fillable[$field] = $data[$field];
                }
            }

            if (isset($data['trigger_type'])) {
                $triggerType = DripTriggerCatalog::normalizeType($data['trigger_type']);
                $fillable['trigger_type'] = $triggerType;
                $fillable['trigger_options'] = DripTriggerCatalog::sanitizeOptions(
                    $triggerType,
                    (array) ($data['trigger_options'] ?? []),
                );
            }

            if (array_key_exists('audience_id', $data)) {
                $fillable['audience_id'] = $data['audience_id'];
            }

            foreach (['start_date', 'end_date'] as $dateField) {
                if (array_key_exists($dateField, $data)) {
                    $fillable[$dateField] = $data[$dateField] ?: null;
                }
            }

            if ($fillable !== []) {
                $campaign->update($fillable);
            }

            return $campaign->refresh();
        });
    }

    public function toggle(DripCampaign $campaign): DripCampaign
    {
        $newStatus = $campaign->isActive()
            ? ChatbotFlowStatus::Inactive
            : ChatbotFlowStatus::Active;

        $campaign->update(['status' => $newStatus]);

        if (! $campaign->isActive()) {
            $this->cacheManager->forgetNodeMap($campaign->id);
        }

        return $campaign->refresh();
    }

    public function delete(DripCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $this->cacheManager->forgetNodeMap($campaign->id);
            $campaign->stats()->delete();
            $campaign->states()->delete();
            $campaign->delete();
        });
    }

    public function duplicate(DripCampaign $campaign): DripCampaign
    {
        return DB::transaction(function () use ($campaign): DripCampaign {
            $clone = $campaign->replicate(['uuid', 'published_at']);
            $clone->name = $campaign->name . ' (Copy)';
            $clone->status = ChatbotFlowStatus::Draft;
            $clone->save();

            return $clone;
        });
    }
}
