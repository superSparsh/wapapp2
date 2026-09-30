<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

use Illuminate\Support\Carbon;

/**
 * Request-scoped migration window (set by orchestrator for the current run).
 */
final class MigrationScope
{
    public ?Carbon $since = null;

    public function reset(): void
    {
        $this->since = null;
    }
}
