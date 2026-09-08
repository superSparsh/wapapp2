<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $table = 'currencies';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'format',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function formatAmount(float $amount): string
    {
        $format = $this->format !== null && $this->format !== '' ? $this->format : '{PRICE}';

        return str_replace('{PRICE}', number_format($amount, 2), $format);
    }
}
