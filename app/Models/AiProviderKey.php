<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiProvider;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderKey extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'provider',
        'api_key',
        'chat_model',
        'embedding_model',
        'embedding_dimensions',
        'is_active',
        'is_validated',
    ];

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_validated' => 'boolean',
            'embedding_dimensions' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }
}
