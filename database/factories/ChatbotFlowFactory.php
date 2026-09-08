<?php

namespace Database\Factories;

use App\Enums\ChatbotFlowStatus;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatbotFlow>
 */
class ChatbotFlowFactory extends Factory
{
    protected $model = ChatbotFlow::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Bot',
            'status' => ChatbotFlowStatus::Draft,
            'exported_data' => null,
            'published_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ChatbotFlowStatus::Active,
            'published_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => ChatbotFlowStatus::Inactive,
        ]);
    }

    public function withNodes(int $count = 3): static
    {
        $nodes = [];
        for ($i = 1; $i <= $count; $i++) {
            $nodes[] = [
                'id' => "node_{$i}",
                'type' => 'welcomeMessage',
                'data' => ['label' => "Node {$i}", 'message' => "Message {$i}"],
            ];
        }

        return $this->state(fn () => [
            'exported_data' => ['nodes' => $nodes, 'edges' => []],
        ]);
    }

    public function withStats(int $count = 5, string $action = 'entered'): static
    {
        return $this->afterCreating(function (ChatbotFlow $flow) use ($count, $action): void {
            ChatbotFlowStat::factory()
                ->count($count)
                ->for($flow, 'chatbotFlow')
                ->create(['action' => $action]);
        });
    }
}
