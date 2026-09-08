<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'status',
        'audience_id',
        'whatsapp_line_id',
        'template_id',
        'template_variables',
        'scheduled_at',
        'timezone',
        'started_at',
        'completed_at',
        'total_recipients',
        'total_delivered',
        'total_failed',
        'total_read',
        'total_response',
        'total_unsubscribed',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'template_variables' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_recipients' => 'integer',
            'total_delivered' => 'integer',
            'total_failed' => 'integer',
            'total_read' => 'integer',
            'total_response' => 'integer',
            'total_unsubscribed' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(CampaignWebhook::class);
    }

    /** @param Builder<self> $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', CampaignStatus::Draft);
    }

    /** @param Builder<self> $query */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', CampaignStatus::Scheduled);
    }

    /** @param Builder<self> $query */
    public function scopeSending(Builder $query): void
    {
        $query->where('status', CampaignStatus::Sending);
    }

    /** @param Builder<self> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', CampaignStatus::Completed);
    }

    public function isDraft(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    public function isScheduled(): bool
    {
        return $this->status === CampaignStatus::Scheduled;
    }

    public function isSending(): bool
    {
        return $this->status === CampaignStatus::Sending;
    }

    public function isCompleted(): bool
    {
        return $this->status === CampaignStatus::Completed;
    }

    public function isPaused(): bool
    {
        return $this->status === CampaignStatus::Paused;
    }

    public function isCancelled(): bool
    {
        return $this->status === CampaignStatus::Cancelled;
    }

    public function canBeEdited(): bool
    {
        return $this->status?->isEditable() ?? false;
    }

    public function canBeSent(): bool
    {
        return $this->status?->canBeSent() ?? false;
    }

    public function completionRate(): string
    {
        $total = $this->total_recipients;
        if ($total === 0) {
            return '0.00%';
        }

        return number_format(($this->total_delivered / $total) * 100, 2) . '%';
    }
}
