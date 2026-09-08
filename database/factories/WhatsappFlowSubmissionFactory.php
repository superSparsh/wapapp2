<?php

namespace Database\Factories;

use App\Models\WhatsappFlowSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappFlowSubmission>
 */
class WhatsappFlowSubmissionFactory extends Factory
{
    protected $model = WhatsappFlowSubmission::class;

    public function definition(): array
    {
        return [
            'contact_phone' => '+91' . fake()->numerify('##########'),
            'form_data' => [
                'full_name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'purpose' => fake()->randomElement(['Sales', 'Support', 'Feedback']),
            ],
            'status' => 'received',
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'processed_at' => now(),
        ]);
    }
}
