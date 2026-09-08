<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationResponseType;
use App\Enums\ConversationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'conversations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'whatsapp_line_id',
        'contact_id',
        'assigned_user_id',
        'assigned_team_member_id',
        'contact_phone',
        'line_phone',
        'contact_name',
        'status',
        'response_type',
        'active_ai_bot_id',
        'lead_score',
        'qualification_status',
        'unread_count',
        'last_message_at',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'response_type' => ConversationResponseType::class,
            'last_message_at' => 'datetime',
            'replied_at' => 'datetime',
            'unread_count' => 'integer',
            'whatsapp_line_id' => 'integer',
            'contact_id' => 'integer',
            'assigned_user_id' => 'integer',
            'assigned_team_member_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }
}
