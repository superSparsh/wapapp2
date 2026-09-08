<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTokenUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_bot_id',
        'provider',
        'model',
        'request_type',
        'prompt_tokens',
        'completion_tokens',
        'embedding_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost_usd' => 'decimal:8',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'embedding_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'ai_bot_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
