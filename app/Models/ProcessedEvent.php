<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    use UsesCentralConnection;
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'idempotency_key',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
