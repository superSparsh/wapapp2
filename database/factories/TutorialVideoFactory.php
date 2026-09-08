<?php

namespace Database\Factories;

use App\Models\TutorialVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorialVideo>
 */
class TutorialVideoFactory extends Factory
{
    protected $model = TutorialVideo::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'module_name' => 'DASHBOARD',
            'youtube_id' => 'dQw4w9WgXcQ',
            'description' => fake()->paragraph(),
            'duration' => '2:00',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
