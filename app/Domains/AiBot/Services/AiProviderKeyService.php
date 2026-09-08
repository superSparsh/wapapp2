<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Domains\AiBot\Services\Providers\AiProviderInterface;
use App\Domains\AiBot\Services\Providers\AzureOpenAiProvider;
use App\Domains\AiBot\Services\Providers\GeminiProvider;
use App\Domains\AiBot\Services\Providers\OpenAiProvider;
use App\Enums\AiProvider;
use App\Models\AiProviderKey;
use Illuminate\Support\Facades\DB;

class AiProviderKeyService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AiProviderKey
    {
        return DB::transaction(function () use ($data): AiProviderKey {
            return AiProviderKey::query()->create([
                'provider' => $data['provider'],
                'api_key' => $data['api_key'],
                'chat_model' => $data['chat_model'] ?? null,
                'embedding_model' => $data['embedding_model'] ?? null,
                'embedding_dimensions' => $data['embedding_dimensions'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_validated' => false,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiProviderKey $key, array $data): AiProviderKey
    {
        $key->forceFill(array_filter($data, fn ($v) => $v !== null))->save();

        return $key->refresh();
    }

    public function delete(AiProviderKey $key): void
    {
        $key->delete();
    }

    public function validateKey(AiProviderKey $key): bool
    {
        $provider = $this->resolveProvider($key->provider);
        $isValid = $provider->validate($key->api_key);

        $key->forceFill(['is_validated' => $isValid])->save();

        return $isValid;
    }

    public function resolveProvider(AiProvider $provider): AiProviderInterface
    {
        return match ($provider) {
            AiProvider::OpenAI => new OpenAiProvider(),
            AiProvider::Gemini => new GeminiProvider(),
            AiProvider::Azure => new AzureOpenAiProvider(),
        };
    }
}
