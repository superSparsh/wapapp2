<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignupConsent extends Model
{
    protected $fillable = [
        'user_id',
        'step',
        'question_key',
        'answer',
        'meta',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
