<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminNotificationType;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminNotification extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'type',
        'title',
        'body',
        'link',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdminNotificationType::class,
            'data' => 'array',
        ];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AdminNotificationRead::class);
    }
}
