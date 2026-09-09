<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformErrorType;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class PlatformErrorLog extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'module',
        'type',
        'tenant_id',
        'source',
        'message',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PlatformErrorType::class,
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
