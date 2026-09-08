<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Models\SignupForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SignupForm>
 */
class SignupFormFactory extends Factory
{
    protected $model = SignupForm::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'status' => FormStatus::Inactive,
            'fields' => [
                [
                    'type' => 'header',
                    'label' => 'Header',
                    'required' => true,
                    'placeholder' => 'Welcome to our form',
                    'text' => '',
                ],
                [
                    'type' => 'phone',
                    'label' => 'WhatsApp Number',
                    'required' => true,
                    'placeholder' => 'Enter WhatsApp Number',
                    'text' => '',
                ],
            ],
            'embed_settings' => SignupForm::defaultEmbedSettings(),
            'submission_count' => 0,
            'sent_count' => 0,
            'read_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => FormStatus::Active,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => FormStatus::Inactive,
        ]);
    }
}
