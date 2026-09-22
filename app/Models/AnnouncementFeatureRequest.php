<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementFeatureRequest extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'announcement_id',
        'tenant_id',
        'user_id',
        'customer_email',
        'customer_name',
        'plan_name',
        'is_acknowledged',
        'is_viewed',
    ];

    protected function casts(): array
    {
        return [
            'is_acknowledged' => 'boolean',
            'is_viewed' => 'boolean',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
