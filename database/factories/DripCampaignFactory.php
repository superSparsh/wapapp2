<?php

namespace Database\Factories;

use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use App\Models\MailList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DripCampaign>
 */
class DripCampaignFactory extends Factory
{
    protected $model = DripCampaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Drip',
            'status' => ChatbotFlowStatus::Draft,
            'exported_data' => null,
            'published_at' => null,
            'timezone' => 'Asia/Kolkata',
            'start_date' => now()->subDays(7),
            'end_date' => now()->addDays(30),
            'trigger_type' => 'welcome-new-subscriber',
            'trigger_options' => [],
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

    public function withAudience(?MailList $list = null): static
    {
        return $this->state(function () use ($list) {
            $audience = $list ?? MailList::factory()->create();

            return ['audience_id' => $audience->id];
        });
    }

    public function withStats(int $count = 5, string $action = 'entered'): static
    {
        return $this->afterCreating(function (DripCampaign $campaign) use ($count, $action): void {
            DripCampaignStat::factory()
                ->count($count)
                ->for($campaign, 'dripCampaign')
                ->create(['action' => $action]);
        });
    }
}
