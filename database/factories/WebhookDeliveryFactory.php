<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_subscription_id' => WebhookSubscription::factory(),
            'event_type' => 'new_lead',
            'correlation_id' => $this->faker->uuid(),
            'payload' => [
                'event' => 'new_lead',
                'data' => [
                    'id' => (string) $this->faker->randomNumber(9),
                    'name' => $this->faker->name(),
                    'phone' => '91'.$this->faker->numerify('##########'),
                    'message' => $this->faker->sentence(),
                    'created_at' => now()->toIso8601String(),
                ],
                'timestamp' => now()->timestamp,
            ],
            'response_status' => 200,
            'response_body' => '{"success": true}',
            'error_message' => null,
            'status' => WebhookDeliveryStatus::Sent,
            'attempt_count' => 1,
            'duration_ms' => $this->faker->numberBetween(50, 2000),
            'sent_at' => now(),
            'response_received_at' => now(),
            'next_retry_at' => null,
        ];
    }

    public function forSubscription(\App\Models\WebhookSubscription $subscription): static
    {
        return $this->state(['webhook_subscription_id' => $subscription->id]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Pending,
            'response_status' => null,
            'response_body' => null,
            'sent_at' => null,
            'response_received_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Sent,
            'response_status' => 200,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Failed,
            'response_status' => 500,
            'response_body' => 'Internal Server Error',
            'error_message' => 'Server returned 500',
        ]);
    }

    public function retrying(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Retrying,
            'response_status' => null,
            'response_body' => null,
            'next_retry_at' => now()->addMinutes(5),
        ]);
    }
}
