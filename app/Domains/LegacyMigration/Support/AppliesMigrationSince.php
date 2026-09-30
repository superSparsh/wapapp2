<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

trait AppliesMigrationSince
{
    protected function migrationSince(): ?Carbon
    {
        return app(MigrationScope::class)->since;
    }

    protected function applySince(Builder $query, string $table, string $column = 'created_at'): Builder
    {
        $since = $this->migrationSince();
        if ($since === null) {
            return $query;
        }

        $legacy = property_exists($this, 'legacy') ? $this->legacy : null;
        if ($legacy instanceof LegacyConnection && ! $legacy->hasColumn($table, $column)) {
            return $query;
        }

        return $query->where($column, '>=', $since->toDateTimeString());
    }
}
