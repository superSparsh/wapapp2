<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerVariable extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'variable_name',
        'template_code',
        'template_name',
        'whatsapp_line_id',
        'list_id',
        'list_name',
    ];

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }
}
