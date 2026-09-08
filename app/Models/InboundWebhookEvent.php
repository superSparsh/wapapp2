<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundWebhookEvent extends Model
{
    use UsesCentralConnection;
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'idempotency_key',
        'payload',
        'headers',
        'tenant_id',
        'whatsapp_line_id',
        'status',
        'retry_count',
        'error_message',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => InboundWebhookEventType::class,
            'status' => InboundWebhookStatus::class,
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
