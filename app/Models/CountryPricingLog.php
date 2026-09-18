<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryPricingLog extends Model
{
    use UsesCentralConnection;

    protected $table = 'country_pricing_logs';

    protected $fillable = [
        'country_code',
        'conversation',
        'old_price',
        'new_price',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:4',
            'new_price' => 'decimal:4',
            'updated_by' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
