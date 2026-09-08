<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Campaign',
            'status' => CampaignStatus::Draft,
            'timezone' => 'Asia/Kolkata',
            'template_variables' => null,
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'total_recipients' => 0,
            'total_delivered' => 0,
            'total_failed' => 0,
            'total_read' => 0,
            'total_response' => 0,
            'total_unsubscribed' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => CampaignStatus::Draft]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => now()->addDays(3),
        ]);
    }

    public function sending(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Sending,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Completed,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Paused,
            'started_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => CampaignStatus::Cancelled]);
    }

    public function withAudience(?MailList $list = null): static
    {
        return $this->state(function () use ($list) {
            $audience = $list ?? MailList::factory()->create();

            return ['audience_id' => $audience->id];
        });
    }

    public function withLine(?WhatsappLine $line = null): static
    {
        return $this->state(function () use ($line) {
            $whatsappLine = $line ?? WhatsappLine::factory()->create();

            return ['whatsapp_line_id' => $whatsappLine->id];
        });
    }

    public function withTemplate(?Template $template = null): static
    {
        return $this->state(function () use ($template) {
            $tpl = $template ?? Template::factory()->create();

            return ['template_id' => $tpl->id];
        });
    }

    public function withStats(int $recipients = 10, int $delivered = 0, int $failed = 0, int $read = 0, int $response = 0): static
    {
        return $this->state(fn () => [
            'total_recipients' => $recipients,
            'total_delivered' => $delivered ?: (int) ($recipients * 0.8),
            'total_failed' => $failed ?: (int) ($recipients * 0.1),
            'total_read' => $read ?: (int) ($recipients * 0.7),
            'total_response' => $response ?: (int) ($recipients * 0.5),
        ]);
    }
}
