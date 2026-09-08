<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PageLayout extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'alias',
        'html',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
