<?php

declare(strict_types=1);

namespace App\Domains\Audience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Option rows intentionally omit SoftDeletes / public UUID — the table has neither column.
 */
class ListFieldOption extends Model
{
    protected $table = 'list_field_options';

    protected $fillable = [
        'list_field_id',
        'label',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ListField::class, 'list_field_id');
    }
}
