<?php

namespace App\Inbox\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'participant_id',
        'subject',
        'last_message_at',
        'status',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the user that owns the conversation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the participant in the conversation.
     */
    public function participant()
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    /**
     * Get all messages for this conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
