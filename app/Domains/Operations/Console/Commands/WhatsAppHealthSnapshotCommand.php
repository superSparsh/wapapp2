<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Domains\Integration\Services\LineProfileService;
use App\Domains\Operations\Services\WhatsAppHealthAlertService;
use App\Models\WhatsappLine;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WhatsAppHealthSnapshotCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'operations:whatsapp-health-snapshot
        {--tenants=* : Tenant IDs to process}
        {--delay=750 : Milliseconds to wait between CustSpace syncs (CAMS rate limit)}';

    protected $description = 'Sync line quality/tier, write WA health snapshots, and create typed alerts.';

    public function handle(
        WhatsAppHealthAlertService $alertService,
        LineProfileService $lineProfile,
    ): int {
        $linesChecked = 0;
        $tenantsScanned = 0;
        $spacesSynced = 0;
        $alertsTouched = 0;
        $delayMs = max(0, (int) $this->option('delay'));

        $this->foreachTenant(function ($tenant) use ($alertService, $lineProfile, $delayMs, &$linesChecked, &$tenantsScanned, &$spacesSynced, &$alertsTouched): void {
            $tenantsScanned++;
            $tenantId = (string) $tenant->id;
            $syncedSpaces = [];

            foreach (WhatsappLine::query()->orderByDesc('is_default')->get() as $line) {
                $oldQuality = $line->quality_rating;
                $spaceKey = $this->spaceKey($line);

                if ($spaceKey !== '' && isset($syncedSpaces[$spaceKey])) {
                    $line->refresh();
                    $alertService->recordLine($line, $tenantId, $oldQuality);
                    $linesChecked++;

                    continue;
                }

                try {
                    $lineProfile->syncQualityFromProvider($line);
                    $line->refresh();
                    $spacesSynced++;
                    if ($spaceKey !== '') {
                        $syncedSpaces[$spaceKey] = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp health sync failed', [
                        'line_id' => $line->id,
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("Sync failed line {$line->id}: ".$e->getMessage());
                }

                $alertService->recordLine($line, $tenantId, $oldQuality);
                $linesChecked++;

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }

            $alertsTouched += $alertService->scanTemplateAlerts($tenantId);
        });

        $this->info("WhatsApp health snapshot done. Tenants={$tenantsScanned}, spaces={$spacesSynced}, lines={$linesChecked}, template alerts scanned={$alertsTouched}.");

        return self::SUCCESS;
    }

    private function spaceKey(WhatsappLine $line): string
    {
        $cust = trim((string) ($line->alibaba_cust_space_id ?? ''));
        if ($cust !== '') {
            return 'cust:'.$cust;
        }

        $waba = trim((string) ($line->waba_id ?? ''));
        if ($waba !== '') {
            return 'waba:'.$waba;
        }

        return 'line:'.$line->id;
    }
}
