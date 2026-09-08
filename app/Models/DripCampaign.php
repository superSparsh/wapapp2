<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Drip\Support\DripTriggerCatalog;
use App\Enums\ChatbotFlowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DripCampaign extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'exported_data',
        'created_by',
        'published_at',
        'timezone',
        'start_date',
        'end_date',
        'audience_id',
        'trigger_type',
        'trigger_options',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChatbotFlowStatus::class,
            'exported_data' => 'array',
            'trigger_options' => 'array',
            'published_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by');
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'audience_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(DripCampaignState::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(DripCampaignStat::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ChatbotFlowStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInactive(Builder $query): void
    {
        $query->where('status', ChatbotFlowStatus::Inactive);
    }

    public function isActive(): bool
    {
        return $this->status === ChatbotFlowStatus::Active;
    }

    public function isWithinDateRange(): bool
    {
        $now = now();

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }

    public function hasFlowData(): bool
    {
        $data = $this->exported_data;

        if (empty($data) || ! is_array($data)) {
            return false;
        }

        if (! empty($data['nodes']) && is_array($data['nodes'])) {
            return true;
        }

        return ! empty($data['drawflow']['Home']['data']);
    }

    public function nodeCount(): int
    {
        $data = $this->exported_data;

        if (! is_array($data)) {
            return 0;
        }

        if (! empty($data['nodes']) && is_array($data['nodes'])) {
            return count($data['nodes']);
        }

        if (! empty($data['drawflow']['Home']['data'])) {
            return count($data['drawflow']['Home']['data']);
        }

        return 0;
    }

    public function triggerLabel(): string
    {
        return DripTriggerCatalog::treeLabel($this->trigger_type);
    }

    public function completionRate(): string
    {
        $entered = $this->stats_count ?? 0;
        if ($entered === 0) {
            return '0.00%';
        }

        $completed = (int) ($this->completed_stats_count ?? 0);

        return number_format(($completed / $entered) * 100, 2) . '%';
    }
}
