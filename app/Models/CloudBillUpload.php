<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudBillUpload extends Model
{
    use HasPublicUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'uuid',
        'filename',
        'path',
        'period',
        'amount',
        'currency',
        'exchange_rate',
        'status',
        'uploaded_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}
