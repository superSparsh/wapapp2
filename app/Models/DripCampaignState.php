<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatbotFlowStateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripCampaignState extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'drip_campaign_id',
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

    public function dripCampaign(): BelongsTo
    {
        return $this->belongsTo(DripCampaign::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ChatbotFlowStateStatus::Active);
    }
}
