<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationResponseType;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends TenantModel
{
    use HasFactory;
    protected $fillable = [
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
        ];
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedTeamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assigned_team_member_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
