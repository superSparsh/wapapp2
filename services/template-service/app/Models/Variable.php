<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VariableDataType;
use App\Enums\VariableType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Variable extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
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

    protected static function booted(): void
    {
        static::creating(function (Variable $variable): void {
            if (empty($variable->uuid)) {
                $variable->uuid = (string) Str::uuid();
            }
        });
    }
}
