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

    protected $signature = 'operations:whatsapp-health-snapshot {--tenants=* : Tenant IDs to process}';

    protected $description = 'Sync line quality/tier, write WA health snapshots, and create typed alerts.';

    public function handle(
        WhatsAppHealthAlertService $alertService,
        LineProfileService $lineProfile,
    ): int {
        $linesChecked = 0;
        $tenantsScanned = 0;
        $alertsTouched = 0;

        $this->foreachTenant(function ($tenant) use ($alertService, $lineProfile, &$linesChecked, &$tenantsScanned, &$alertsTouched): void {
            $tenantsScanned++;
            $tenantId = (string) $tenant->id;

            foreach (WhatsappLine::query()->get() as $line) {
                $oldQuality = $line->quality_rating;

                try {
                    $lineProfile->syncFromProvider($line);
                    $line->refresh();
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp health sync failed', [
                        'line_id' => $line->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $alertService->recordLine($line, $tenantId, $oldQuality);
                $linesChecked++;
            }

            $alertsTouched += $alertService->scanTemplateAlerts($tenantId);
        });

        $this->info("WhatsApp health snapshot done. Tenants={$tenantsScanned}, lines={$linesChecked}, template alerts scanned={$alertsTouched}.");

        return self::SUCCESS;
    }
}
