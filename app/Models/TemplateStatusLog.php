<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Templates\Enums\TemplateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateStatusLog extends Model
{
    protected $fillable = [
        'template_id',
        'previous_status',
        'new_status',
        'reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'previous_status' => TemplateStatus::class,
            'new_status' => TemplateStatus::class,
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
