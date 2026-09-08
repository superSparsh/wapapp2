<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsappFlowStatus;
use App\Enums\WhatsappFlowSubmitAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappFlow extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'meta_flow_id',
        'flow_json',
        'meta_json',
        'json_asset_path',
        'data_exchange_endpoint',
        'exchange_token',
        'categories',
        'draft_synced_at',
        'cust_space_id',
        'whatsapp_line_id',
        'created_by',
        'published_at',
        'audience_id',
        'on_submit_action',
        'on_submit_webhook_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsappFlowStatus::class,
            'on_submit_action' => WhatsappFlowSubmitAction::class,
            'flow_json' => 'array',
            'meta_json' => 'array',
            'categories' => 'array',
            'published_at' => 'datetime',
            'draft_synced_at' => 'datetime',
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

    public function audience(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'audience_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WhatsappFlowSubmission::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', WhatsappFlowStatus::Active);
    }

    /** @param Builder<self> $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', WhatsappFlowStatus::Draft);
    }

    /** @param Builder<self> $query */
    public function scopeArchived(Builder $query): void
    {
        $query->where('status', WhatsappFlowStatus::Archived);
    }

    // ─── Helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === WhatsappFlowStatus::Active;
    }

    public function isDraftSynced(): bool
    {
        return $this->draft_synced_at !== null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function screenCount(): int
    {
        $data = $this->flow_json;

        if (! is_array($data) || ! isset($data['screens'])) {
            return 0;
        }

        return count($data['screens']);
    }

    public function fieldCount(): int
    {
        $data = $this->flow_json;

        if (! is_array($data) || ! isset($data['screens'])) {
            return 0;
        }

        $count = 0;

        foreach ($data['screens'] as $screen) {
            $count += count($screen['fields'] ?? []);
        }

        return $count;
    }

    public function submissionCount(): int
    {
        return $this->submissions()->count();
    }
}
