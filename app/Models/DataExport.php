<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataExportStatus;

class DataExport extends TenantModel
{
    protected $fillable = [
        'data_age',
        'modules',
        'status',
        'file_path',
        'file_size',
        'expires_at',
        'completed_at',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'status' => DataExportStatus::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
