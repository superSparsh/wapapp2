<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Audience\Models\ContactTag;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactTag>
 */
class ContactTagFactory extends Factory
{
    protected $model = ContactTag::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
