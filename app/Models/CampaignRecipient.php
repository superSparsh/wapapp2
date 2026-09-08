<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'contact_phone',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'responded_at',
        'failed_at',
        'failure_reason',
        'unsubscribed_at',
        'message_id',
        'variable_values',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'variable_values' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'responded_at' => 'datetime',
            'failed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /** @param Builder<self> $query */
    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CampaignRecipientStatus::Pending);
    }

    /** @param Builder<self> $query */
    public function scopeDelivered(Builder $query): void
    {
        $query->where('status', CampaignRecipientStatus::Delivered);
    }

    /** @param Builder<self> $query */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', CampaignRecipientStatus::Failed);
    }

    /** @param Builder<self> $query */
    public function scopeRead(Builder $query): void
    {
        $query->where('status', CampaignRecipientStatus::Read);
    }

    /** @param Builder<self> $query */
    public function scopeResponse(Builder $query): void
    {
        $query->where('status', CampaignRecipientStatus::Response);
    }

    /** @param Builder<self> $query */
    public function scopeSent(Builder $query): void
    {
        $query->where('status', CampaignRecipientStatus::Sent);
    }
}
