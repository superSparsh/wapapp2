<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Enums\VariableType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variable extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'data_type',
        'value',
        'whatsapp_line_id',
        'team_member_id',
        'team_member_name',
    ];

    protected function casts(): array
    {
        return [
            'type' => VariableType::class,
            'data_type' => VariableDataType::class,
        ];
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }
}
