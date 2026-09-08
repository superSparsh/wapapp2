<?php

namespace Database\Factories;

use App\Enums\WhatsappFlowStatus;
use App\Enums\WhatsappFlowSubmitAction;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsappFlow>
 */
class WhatsappFlowFactory extends Factory
{
    protected $model = WhatsappFlow::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Flow',
            'status' => WhatsappFlowStatus::Draft,
            'meta_flow_id' => null,
            'flow_json' => null,
            'data_exchange_endpoint' => null,
            'published_at' => null,
            'on_submit_action' => WhatsappFlowSubmitAction::CreateLead,
        ];
    }

    public function active(): static
    {
        return $this->state(function () {
            $token = (string) Str::uuid();

            return [
                'status' => WhatsappFlowStatus::Active,
                'published_at' => now(),
                'meta_flow_id' => 'flow_'.fake()->unique()->numerify('######'),
                'exchange_token' => $token,
                'data_exchange_endpoint' => url('/v1/flow-exchange/'.$token),
                'draft_synced_at' => now(),
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => WhatsappFlowStatus::Archived,
            'published_at' => now()->subDays(30),
        ]);
    }

    public function withFlowJson(int $screens = 2, int $fieldsPerScreen = 3): static
    {
        $flowJson = ['screens' => [], 'first_screen' => 'screen_1'];

        for ($i = 1; $i <= $screens; $i++) {
            $screenFields = [];

            for ($j = 1; $j <= $fieldsPerScreen; $j++) {
                $screenFields[] = [
                    'name' => "field_{$i}_{$j}",
                    'type' => 'text',
                    'label' => "Field {$j}",
                    'required' => $j === 1,
                ];
            }

            $flowJson['screens'][] = [
                'id' => "screen_{$i}",
                'title' => "Screen {$i}",
                'fields' => $screenFields,
                'next_screen' => $i < $screens ? 'screen_' . ($i + 1) : null,
                'conditions' => [],
            ];
        }

        return $this->state(fn () => ['flow_json' => $flowJson]);
    }

    public function withSubmissions(int $count = 3): static
    {
        return $this->afterCreating(function (WhatsappFlow $flow) use ($count): void {
            WhatsappFlowSubmission::factory()
                ->count($count)
                ->for($flow, 'whatsappFlow')
                ->create();
        });
    }
}
