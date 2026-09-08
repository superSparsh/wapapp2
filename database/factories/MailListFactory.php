<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\MailList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailList>
 */
class MailListFactory extends Factory
{
    protected $model = MailList::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => RecordStatus::Active,
        ];
    }
}
