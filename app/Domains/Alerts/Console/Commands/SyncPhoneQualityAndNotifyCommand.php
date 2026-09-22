<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Integration\Services\LineProfileService;
use App\Models\WhatsappLine;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPhoneQualityAndNotifyCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'alerts:phone-quality-notify {--tenants=* : Tenant IDs to process}';

    protected $description = 'Refresh WhatsApp line quality/tier from CAMS and notify on changes.';

    public function handle(AlertDispatcher $dispatcher, LineProfileService $lineProfile): int
    {
        $drops = 0;
        $tierChanges = 0;
        $linesChecked = 0;
        $tenantsScanned = 0;

        $this->foreachTenant(function () use ($dispatcher, $lineProfile, &$drops, &$tierChanges, &$linesChecked, &$tenantsScanned): void {
            $tenantsScanned++;

            foreach (WhatsappLine::query()->get() as $line) {
                $oldQuality = $line->quality_rating;
                $oldTier = $line->messaging_limit_tier;

                try {
                    $lineProfile->syncFromProvider($line);
                    $line->refresh();
                } catch (\Throwable $e) {
                    Log::warning('Phone quality sync failed', [
                        'line_id' => $line->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $linesChecked++;
                $qualityChanged = (string) $oldQuality !== (string) $line->quality_rating;
                $tierChanged = (string) $oldTier !== (string) $line->messaging_limit_tier;

                if ($qualityChanged || $tierChanged) {
                    if ($qualityChanged) {
                        $drops++;
                    }
                    if ($tierChanged) {
                        $tierChanges++;
                    }
                    $dispatcher->phoneQualityChanged($line, $oldQuality, $oldTier);
                }
            }
        });

        $this->info("Phone quality sync done. Lines={$linesChecked}, quality changes={$drops}, tier changes={$tierChanges}");

        return self::SUCCESS;
    }
}
