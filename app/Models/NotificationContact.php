<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationContactType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'type',
        'contact_info',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationContactType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
