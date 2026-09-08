<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatbotFlowStatAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DripCampaignStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'drip_campaign_id',
        'conversation_id',
        'node_id',
        'node_type',
        'contact_phone',
        'action',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'action' => ChatbotFlowStatAction::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $stat): void {
            if (empty($stat->uuid)) {
                $stat->uuid = (string) Str::uuid();
            }
        });
    }

    public function dripCampaign(): BelongsTo
    {
        return $this->belongsTo(DripCampaign::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
