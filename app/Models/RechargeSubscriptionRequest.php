<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RechargeSubscriptionRequest extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'amount',
        'currency',
        'status',
        'notes',
        'approved_at',
        'approved_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    protected static function booted(): void
    {
        static::created(function (self $request): void {
            try {
                $tenant = $request->tenant;
                $label = (string) ($tenant?->company_name ?: $tenant?->name ?: $request->tenant_id);
                $amount = $request->amount !== null
                    ? trim(($request->currency ?: 'INR').' '.number_format((float) $request->amount, 2))
                    : null;

                app(\App\Domains\Admin\Services\AdminNotificationService::class)->notifyRechargeRequest(
                    requestId: (int) $request->id,
                    tenantLabel: $label,
                    amount: $amount,
                );
            } catch (\Throwable) {
                //
            }
        });
    }
}
