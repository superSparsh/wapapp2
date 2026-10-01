<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMessageFreeUsage extends TenantModel
{
    protected $fillable = [
        'whatsapp_line_id',
        'year_month',
        'used_count',
    ];

    protected function casts(): array
    {
        return [
            'used_count' => 'integer',
        ];
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }
}
