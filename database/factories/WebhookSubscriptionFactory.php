<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookSubscriptionStatus;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->url(),
            'description' => $this->faker->sentence(3),
            'secret_key' => Str::random(32),
            'events' => ['new_lead'],
            'status' => WebhookSubscriptionStatus::Active,
            'audience_list_id' => null,
            'last_triggered_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => WebhookSubscriptionStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => WebhookSubscriptionStatus::Inactive]);
    }
}
