<?php

namespace Database\Factories;

use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'phone' => '91'.fake()->unique()->numerify('##########'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'opt_in_status' => ContactOptInStatus::OptedIn,
            'opted_in_at' => now(),
        ];
    }
}
