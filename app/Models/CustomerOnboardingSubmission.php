<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerOnboardingSubmission extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'uuid',
        'reference',
        'company_name',
        'email',
        'service_label',
        'status',
        'payload',
        'attachment_paths',
        'zoho_response',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attachment_paths' => 'array',
            'zoho_response' => 'array',
        ];
    }
}
