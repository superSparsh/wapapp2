<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessCustomTermStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCustomTerm extends Model
{
    use HasPublicUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id',
        'title',
        'body',
        'status',
        'admin_note',
        'reviewed_by_admin_id',
        'submitted_at',
        'reviewed_at',
        'published_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'status' => BusinessCustomTermStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function isPublished(): bool
    {
        return $this->status === BusinessCustomTermStatus::Approved;
    }
}
