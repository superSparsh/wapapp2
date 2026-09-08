<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiBot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBot>
 */
class AiBotFactory extends Factory
{
    protected $model = AiBot::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Bot',
            'type' => fake()->randomElement(['support', 'sales', 'faq']),
            'system_prompt' => 'You are a helpful assistant.',
            'provider' => AiProvider::OpenAI,
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'temperature' => 0.3,
            'status' => 'active',
            'is_default' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function withGemini(): static
    {
        return $this->state(fn () => [
            'provider' => AiProvider::Gemini,
            'chat_model' => 'gemini-1.5-flash',
            'embedding_model' => 'text-embedding-004',
        ]);
    }
}
