<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'contact_id' => Contact::factory(),
            'contact_phone' => '91' . fake()->numerify('##########'),
            'status' => CampaignRecipientStatus::Pending,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
            'unsubscribed_at' => null,
            'message_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Pending,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Sent,
            'sent_at' => now(),
            'message_id' => 'wamid.' . fake()->uuid(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Delivered,
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
            'message_id' => 'wamid.' . fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Failed,
            'sent_at' => now()->subMinutes(5),
            'failed_at' => now(),
            'failure_reason' => fake()->sentence(3),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Read,
            'sent_at' => now()->subMinutes(10),
            'delivered_at' => now()->subMinutes(8),
            'read_at' => now(),
            'message_id' => 'wamid.' . fake()->uuid(),
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Unsubscribed,
            'sent_at' => now()->subMinutes(10),
            'delivered_at' => now()->subMinutes(8),
            'unsubscribed_at' => now(),
        ]);
    }
}
