<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'whatsapp_line_id' => WhatsappLine::factory(),
            'contact_id' => Contact::factory(),
            'contact_phone' => fn (array $attributes) => Contact::query()->find($attributes['contact_id'])?->phone
                ?? '91'.fake()->numerify('##########'),
            'line_phone' => fn (array $attributes) => WhatsappLine::query()->find($attributes['whatsapp_line_id'])?->phone
                ?? '919999999999',
            'contact_name' => fake()->name(),
            'status' => ConversationStatus::Open,
            'response_type' => 'human_response',
            'unread_count' => 0,
            'last_message_at' => now(),
        ];
    }
}
