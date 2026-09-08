<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Audience\Models\Blacklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blacklist>
 */
class BlacklistFactory extends Factory
{
    protected $model = Blacklist::class;

    public function definition(): array
    {
        return [
            'phone' => '91' . fake()->unique()->numerify('##########'),
            'email' => null,
            'reason' => fake()->optional()->sentence(),
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'phone' => null,
            'email' => fake()->unique()->safeEmail(),
        ]);
    }
}
