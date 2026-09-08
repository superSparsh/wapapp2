<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatbotFlowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotFlow extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'exported_data',
        'whatsapp_line_id',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChatbotFlowStatus::class,
            'exported_data' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by');
    }

    public function states(): HasMany
    {
        return $this->hasMany(ChatbotFlowState::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(ChatbotFlowStat::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

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

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    // ─── Helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === ChatbotFlowStatus::Active;
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
}
