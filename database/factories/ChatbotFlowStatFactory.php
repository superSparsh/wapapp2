<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChatbotFlowStatAction;
use App\Models\ChatbotFlowStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatbotFlowStat>
 */
class ChatbotFlowStatFactory extends Factory
{
    protected $model = ChatbotFlowStat::class;

    public function definition(): array
    {
        return [
            'node_id' => 'node_' . fake()->numberBetween(1, 5),
            'node_type' => fake()->randomElement(['welcomeMessage', 'templateMessage', 'condition', 'delay']),
            'contact_phone' => '91' . fake()->numerify('##########'),
            'action' => ChatbotFlowStatAction::Entered,
            'metadata' => null,
        ];
    }

    public function entered(): static
    {
        return $this->state(fn () => ['action' => ChatbotFlowStatAction::Entered]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['action' => ChatbotFlowStatAction::Completed]);
    }

    public function dropped(): static
    {
        return $this->state(fn () => ['action' => ChatbotFlowStatAction::Dropped]);
    }

    public function error(): static
    {
        return $this->state(fn () => ['action' => ChatbotFlowStatAction::Error]);
    }

    public function forPhone(string $phone): static
    {
        return $this->state(fn () => ['contact_phone' => $phone]);
    }

    public function forNode(string $nodeId, string $nodeType = 'welcomeMessage'): static
    {
        return $this->state(fn () => [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
        ]);
    }
}
