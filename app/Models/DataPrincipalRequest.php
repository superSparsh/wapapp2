<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataPrincipalRequestStatus;
use App\Enums\DataPrincipalRequestType;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataPrincipalRequest extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'requester_name',
        'requester_email',
        'requester_phone',
        'request_type',
        'description',
        'nominee_name',
        'nominee_email',
        'nominee_phone',
        'nominee_relation',
        'status',
        'admin_note',
        'handled_by_admin_id',
        'due_at',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'request_type' => DataPrincipalRequestType::class,
            'status' => DataPrincipalRequestStatus::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function handledByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
