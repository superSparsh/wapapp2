<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataDeletionStatus;

class DataDeletionSchedule extends TenantModel
{
    protected $fillable = [
        'data_age',
        'modules',
        'schedule_delay',
        'export_before_delete',
        'scheduled_for',
        'status',
        'records_deleted',
        'completed_at',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'export_before_delete' => 'boolean',
            'scheduled_for' => 'datetime',
            'status' => DataDeletionStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
