<?php

declare(strict_types=1);

namespace App\Domains\Drip\Jobs;

use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Domains\Drip\Support\DripSchedule;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Enroll audience contacts for a scheduled drip that became Active after its due time.
 */
class EnrollDripAudienceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $campaignId,
    ) {}

    public function handle(DripTriggerDispatcher $dispatcher, DripSchedule $schedule): void
    {
        /** @var Tenant|null $tenant */
        $tenant = tenancy()->central(fn () => Tenant::query()->find($this->tenantId));
        if ($tenant === null) {
            return;
        }

        $alreadyOnTenant = tenancy()->initialized
            && (string) tenant('id') === (string) $this->tenantId;

        if (! $alreadyOnTenant) {
            tenancy()->initialize($tenant);
        }

        try {
            $campaign = DripCampaign::query()->find($this->campaignId);
            if ($campaign === null || ! $campaign->isActive() || ! $campaign->hasFlowData()) {
                return;
            }

            $enrolled = 0;
            Contact::query()
                ->when($campaign->audience_id, fn ($q) => $q->where('mail_list_id', $campaign->audience_id))
                ->orderBy('id')
                ->chunkById(100, function ($contacts) use ($campaign, $dispatcher, $schedule, &$enrolled): void {
                    foreach ($contacts as $contact) {
                        $key = $schedule->enrollmentKey($campaign, $contact);
                        if ($key === null) {
                            continue;
                        }
                        if ($dispatcher->enroll($campaign, $contact, enrollmentKey: $key)) {
                            $enrolled++;
                        }
                    }
                });

            Log::info('drip.audience_enroll_on_activate', [
                'tenant_id' => $this->tenantId,
                'campaign_id' => $this->campaignId,
                'enrolled' => $enrolled,
            ]);
        } finally {
            if (! $alreadyOnTenant && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
