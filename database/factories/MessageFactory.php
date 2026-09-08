<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'body' => fake()->sentence(),
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'delivered_at' => now(),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
