<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookSubscriptionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookSubscription extends TenantModel
{
    protected $fillable = [
        'whatsapp_line_id',
        'url',
        'description',
        'secret_key',
        'events',
        'status',
        'audience_list_id',
        'last_triggered_at',
    ];

    protected $hidden = [
        'secret_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebhookSubscriptionStatus::class,
            'events' => 'array',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
