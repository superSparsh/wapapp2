<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatbotFlowStateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotFlowState extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'chatbot_flow_id',
        'current_node_id',
        'variables',
        'status',
        'expires_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'status' => ChatbotFlowStateStatus::class,
            'expires_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function chatbotFlow(): BelongsTo
    {
        return $this->belongsTo(ChatbotFlow::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ChatbotFlowStateStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWaiting(Builder $query): void
    {
        $query->where('status', ChatbotFlowStateStatus::Waiting);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', ChatbotFlowStateStatus::Expired);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForConversation(Builder $query, int $conversationId): void
    {
        $query->where('conversation_id', $conversationId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markExpired(): void
    {
        $this->forceFill([
            'status' => ChatbotFlowStateStatus::Expired,
            'processed_at' => now(),
        ])->save();
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => ChatbotFlowStateStatus::Completed,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mergeVariables(array $data): void
    {
        $current = $this->variables ?? [];
        $this->forceFill(['variables' => array_merge($current, $data)])->save();
    }
}
