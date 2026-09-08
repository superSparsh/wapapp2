<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignWebhook extends Model
{
    protected $fillable = [
        'campaign_id',
        'url',
        'secret_key',
        'events',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
