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

        try {
            $ref = (int) tenancy()->central(function () use ($tenantId, $campaignId, $uuid) {
                $existingRow = DB::table('campaign_worker_refs')
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_campaign_id', $campaignId)
                    ->value('id');

                if ($existingRow) {
                    return (int) $existingRow;
                }

                return (int) DB::table('campaign_worker_refs')->insertGetId([
                    'tenant_id' => $tenantId,
                    'tenant_campaign_id' => $campaignId > 0 ? $campaignId : null,
                    'campaign_uuid' => $uuid !== '' ? $uuid : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
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
}
