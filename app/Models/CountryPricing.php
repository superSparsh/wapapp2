<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CountryPricing extends Model
{
    use HasPublicUuid;
    use UsesCentralConnection;

    protected $table = 'country_pricing';

    protected $fillable = [
        'uuid',
        'admin_id',
        'country_name',
        'country_code',
        'dial_code',
        'region_codes',
        'currency',
        'marketing_price',
        'utility_price',
        'auth_price',
        'auth_international_price',
        'service_price',
        'tekpro_marketing_price',
        'tekpro_utility_price',
        'tekpro_auth_price',
        'tekpro_auth_international_price',
        'tekpro_service_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'admin_id' => 'integer',
            'region_codes' => 'array',
            'marketing_price' => 'decimal:4',
            'utility_price' => 'decimal:4',
            'auth_price' => 'decimal:4',
            'auth_international_price' => 'decimal:4',
            'service_price' => 'decimal:4',
            'tekpro_marketing_price' => 'float',
            'tekpro_utility_price' => 'float',
            'tekpro_auth_price' => 'float',
            'tekpro_auth_international_price' => 'float',
            'tekpro_service_price' => 'float',
            'status' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CountryPricingLog::class, 'country_code', 'country_code');
    }

    /**
     * @return array<string, string>
     */
    public static function priceFields(): array
    {
        return [
            'marketing_price' => 'marketing',
            'utility_price' => 'utility',
            'auth_price' => 'auth',
            'auth_international_price' => 'auth_international',
            'service_price' => 'service',
            'tekpro_marketing_price' => 'tekpro_marketing',
            'tekpro_utility_price' => 'tekpro_utility',
            'tekpro_auth_price' => 'tekpro_auth',
            'tekpro_auth_international_price' => 'tekpro_auth_international',
            'tekpro_service_price' => 'tekpro_service',
        ];
    }
}
