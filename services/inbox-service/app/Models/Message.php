<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Message extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'messages';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'conversation_id',
        'external_message_id',
        'body',
        'direction',
        'message_type',
        'status',
        'template_id',
        'chatbot_flow_id',
        'meta_flow_id',
        'failed_reason',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'message_type' => MessageType::class,
            'status' => MessageStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'conversation_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
