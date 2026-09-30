<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Allocates a globally unique sequential integer (central campaign_worker_refs.id)
 * and stores it on the tenant campaign as worker_ref for OCI lifecycle tracking.
 *
 * Uses the central DB connection directly — never tenancy()->central() — so an open
 * tenant transaction (e.g. CampaignService::duplicate) is not rolled back.
 */
final class CampaignWorkerRefAllocator
{
    public function ensure(Campaign $campaign): ?int
    {
        $existing = (int) ($campaign->worker_ref ?? 0);
        if ($existing > 0) {
            return $existing;
        }

        $tenantId = tenant('id');
        if (! is_string($tenantId) || trim($tenantId) === '') {
            Log::warning('Campaign worker_ref allocation skipped - no tenant context', [
                'campaign_id' => $campaign->id,
            ]);

            return null;
        }

        $tenantId = trim($tenantId);
        $campaignId = (int) $campaign->id;
        $uuid = trim((string) ($campaign->uuid ?? ''));

        if ($campaignId < 1) {
            return null;
        }

        try {
            $central = DB::connection($this->centralConnection());

            $existingRow = $central->table('campaign_worker_refs')
                ->where('tenant_id', $tenantId)
                ->where('tenant_campaign_id', $campaignId)
                ->value('id');

            $ref = $existingRow
                ? (int) $existingRow
                : (int) $central->table('campaign_worker_refs')->insertGetId([
                    'tenant_id' => $tenantId,
                    'tenant_campaign_id' => $campaignId,
                    'campaign_uuid' => $uuid !== '' ? $uuid : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::error('Campaign worker_ref allocation failed', [
                'campaign_id' => $campaignId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($ref < 1) {
            return null;
        }

        if ($campaign->exists) {
            $campaign->forceFill(['worker_ref' => $ref])->saveQuietly();
        } else {
            $campaign->worker_ref = $ref;
        }

        return $ref;
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default', 'mysql'));
    }
}
