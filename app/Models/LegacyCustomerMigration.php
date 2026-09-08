<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyCustomerMigration extends Model
{
    protected $table = 'legacy_customer_migrations';

    protected $fillable = [
        'legacy_customer_id',
        'legacy_customer_uid',
        'legacy_email',
        'tenant_id',
        'status',
        'preview',
        'report',
        'started_at',
        'completed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'preview' => 'array',
            'report' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
