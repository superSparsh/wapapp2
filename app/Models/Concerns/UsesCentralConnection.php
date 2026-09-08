<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait UsesCentralConnection
{
    public function getConnectionName(): ?string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }
}
