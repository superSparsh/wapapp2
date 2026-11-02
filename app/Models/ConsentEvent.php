<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentEventType;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsentEvent extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'subject_email',
        'event_type',
        'purpose',
        'channel',
        'source',
        'document_url',
        'meta',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ConsentEventType::class,
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
