<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasPublicUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'uuid',
        'title',
        'body',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
