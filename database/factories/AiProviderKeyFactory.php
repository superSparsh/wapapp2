<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiProviderKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderKey>
 */
class AiProviderKeyFactory extends Factory
{
    protected $model = AiProviderKey::class;

    public function definition(): array
    {
        return [
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-test-' . fake()->regexify('[a-zA-Z0-9]{32}'),
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'is_active' => true,
            'is_validated' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function validated(): static
    {
        return $this->state(fn () => ['is_validated' => true]);
    }
}
