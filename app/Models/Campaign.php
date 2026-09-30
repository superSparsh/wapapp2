<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends TenantModel
{
    use HasFactory;

    protected $fillable = [
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
        'worker_ref',
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
            'worker_ref' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Campaign $campaign): void {
            try {
                app(\App\Domains\Campaigns\Services\CampaignWorkerRefAllocator::class)->ensure($campaign);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Campaign worker_ref allocate on create failed', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function audience(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'audience_id');
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(CampaignWebhook::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

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

    // ─── Helpers ───────────────────────────────────────────────────

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

    public function isFailed(): bool
    {
        return $this->status === CampaignStatus::Failed;
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

    /**
     * Wall-clock seconds the campaign has been / was running (started_at → completed_at|now).
     */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->completed_at ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }

    public function durationForHumans(): ?string
    {
        $seconds = $this->durationSeconds();
        if ($seconds === null) {
            return null;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%dh %dm', $h, $m);
        }
        if ($m > 0) {
            return sprintf('%dm %ds', $m, $s);
        }

        return sprintf('%ds', $s);
    }
}
