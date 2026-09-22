<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class WaHealthSnapshot extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id',
        'whatsapp_line_id',
        'phone',
        'quality_rating',
        'messaging_limit_tier',
        'line_status',
        'tier_limit',
        'usage_24h',
        'snapshot_date',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'tier_limit' => 'integer',
            'usage_24h' => 'integer',
        ];
    }
}
