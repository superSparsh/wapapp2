<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorialVideo extends Model
{
    use HasFactory;
    use UsesCentralConnection;

    protected $fillable = [
        'title',
        'module_name',
        'youtube_id',
        'description',
        'duration',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isLocalFile(): bool
    {
        return str_contains((string) $this->youtube_id, '.');
    }
}
