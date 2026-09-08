<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiBot extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'system_prompt',
        'provider',
        'chat_model',
        'embedding_model',
        'temperature',
        'business_information',
        'status',
        'is_default',
        'whatsapp_line_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'temperature' => 'float',
            'provider' => AiProvider::class,
        ];
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function tokenUsageLogs(): HasMany
    {
        return $this->hasMany(AiTokenUsageLog::class, 'ai_bot_id');
    }

    public function businessInfoEntries(): HasMany
    {
        return $this->hasMany(AiBusinessInfo::class, 'ai_bot_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDefaults(Builder $query): void
    {
        $query->where('is_default', true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Resolve the provider configuration for this bot.
     *
     * @return array{provider: string, api_key: ?string, chat_model: string, embedding_model: string}
     */
    public function resolveProvider(): array
    {
        $providerKey = AiProviderKey::query()
            ->where('provider', $this->provider->value)
            ->where('is_active', true)
            ->first();

        if ($providerKey !== null) {
            return [
                'provider' => $this->provider->value,
                'api_key' => $providerKey->api_key,
                'chat_model' => $this->chat_model ?? $providerKey->chat_model ?? 'gpt-4o-mini',
                'embedding_model' => $this->embedding_model ?? $providerKey->embedding_model ?? 'text-embedding-3-small',
            ];
        }

        $anyKey = AiProviderKey::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($anyKey !== null) {
            return [
                'provider' => $anyKey->provider,
                'api_key' => $anyKey->api_key,
                'chat_model' => $anyKey->chat_model ?? 'gpt-4o-mini',
                'embedding_model' => $anyKey->embedding_model ?? 'text-embedding-3-small',
            ];
        }

        return [
            'provider' => $this->provider->value,
            'api_key' => null,
            'chat_model' => $this->chat_model ?? 'gpt-4o-mini',
            'embedding_model' => $this->embedding_model ?? 'text-embedding-3-small',
        ];
    }
}
