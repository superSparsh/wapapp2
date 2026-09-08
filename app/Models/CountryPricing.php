<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class CountryPricing extends Model
{
    use HasPublicUuid;
    use UsesCentralConnection;

    protected $table = 'country_pricing';

    protected $fillable = [
        'uuid',
        'country_code',
        'country_name',
        'marketing_rate',
        'utility_rate',
        'authentication_rate',
        'service_rate',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'marketing_rate' => 'decimal:4',
            'utility_rate' => 'decimal:4',
            'authentication_rate' => 'decimal:4',
            'service_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
