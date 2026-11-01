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
        'legacy_bot_id',
        'legacy_bot_uid',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'temperature' => 'float',
            'provider' => AiProvider::class,
            'legacy_bot_id' => 'integer',
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
            ->orderByDesc('is_validated')
            ->orderByDesc('id')
            ->first();

        $isTittu = $this->provider === AiProvider::Tittu;
        $defaultChat = $isTittu ? 'auto' : 'gpt-4o-mini';
        $defaultEmbed = $isTittu ? 'auto' : 'text-embedding-3-small';

        if ($providerKey !== null) {
            return [
                'provider' => $this->provider->value,
                'api_key' => $providerKey->api_key,
                'chat_model' => $this->normalizeTittuModel(
                    $this->chat_model ?: ($providerKey->chat_model ?: $defaultChat),
                    $defaultChat,
                    $isTittu
                ),
                'embedding_model' => $this->normalizeTittuModel(
                    $this->embedding_model ?: ($providerKey->embedding_model ?: $defaultEmbed),
                    $defaultEmbed,
                    $isTittu
                ),
            ];
        }

        $anyKey = AiProviderKey::query()
            ->where('is_active', true)
            ->orderByDesc('is_validated')
            ->orderByDesc('id')
            ->first();

        if ($anyKey !== null) {
            $provider = $anyKey->provider instanceof AiProvider
                ? $anyKey->provider->value
                : (string) $anyKey->provider;

            return [
                'provider' => $provider,
                'api_key' => $anyKey->api_key,
                'chat_model' => $this->normalizeTittuModel(
                    $anyKey->chat_model ?: $defaultChat,
                    $defaultChat,
                    $isTittu
                ),
                'embedding_model' => $this->normalizeTittuModel(
                    $anyKey->embedding_model ?: $defaultEmbed,
                    $defaultEmbed,
                    $isTittu
                ),
            ];
        }

        return [
            'provider' => $this->provider->value,
            'api_key' => null,
            'chat_model' => $this->normalizeTittuModel($this->chat_model ?: $defaultChat, $defaultChat, $isTittu),
            'embedding_model' => $this->normalizeTittuModel($this->embedding_model ?: $defaultEmbed, $defaultEmbed, $isTittu),
        ];
    }

    /**
     * Tittu Gateway rejects meta names like "tittu" as targetProvider.
     * Coerce those (and blanks) back to auto for chat/embedding.
     */
    private function normalizeTittuModel(?string $model, string $default, bool $isTittu): string
    {
        $value = trim((string) $model);
        if ($value === '') {
            return $default;
        }

        if (! $isTittu) {
            return $value;
        }

        $normalized = strtolower(str_replace(' ', '_', $value));
        $meta = [
            'auto',
            'tittu',
            'tittu_gateway',
            'tittu-gateway',
            'gateway',
            'openai',
            'gemini',
            'azure',
            'google',
        ];

        return in_array($normalized, $meta, true) ? $default : $value;
    }
}
