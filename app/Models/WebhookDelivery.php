<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'uuid',
        'webhook_subscription_id',
        'whatsapp_line_id',
        'event_type',
        'correlation_id',
        'payload',
        'response_status',
        'response_body',
        'error_message',
        'status',
        'attempt_count',
        'duration_ms',
        'sent_at',
        'response_received_at',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebhookDeliveryStatus::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
            'response_received_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookDelivery $delivery): void {
            if (empty($delivery->uuid)) {
                $delivery->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }
}
