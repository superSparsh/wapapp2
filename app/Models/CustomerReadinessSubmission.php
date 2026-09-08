<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReadinessSubmission extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'uuid',
        'customer_name',
        'customer_email',
        'business_name',
        'business_email',
        'website',
        'doc_type',
        'status',
        'data',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'meta' => 'array',
        ];
    }
}
