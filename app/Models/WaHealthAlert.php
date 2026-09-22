<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class WaHealthAlert extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id',
        'whatsapp_line_id',
        'template_id',
        'alert_type',
        'severity',
        'title',
        'body',
        'dedupe_key',
        'is_read',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
