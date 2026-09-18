<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use App\Domains\Admin\Services\MaintenanceModeService;

trait RespectsMaintenanceModules
{
    protected function maintenanceAllows(string $module): bool
    {
        return app(MaintenanceModeService::class)->moduleEnabled($module);
    }

    protected function skipForMaintenance(string $module, string $label = ''): bool
    {
        if ($this->maintenanceAllows($module)) {
            return false;
        }

        if (property_exists($this, 'components') && isset($this->components) && method_exists($this, 'info')) {
            $this->info(($label !== '' ? $label.' ' : '').'Skipped — maintenance mode paused module ['.$module.'].');
        }

        return true;
    }
}
