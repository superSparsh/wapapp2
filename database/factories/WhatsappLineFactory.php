<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\WhatsappLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<WhatsappLine>
 */
class WhatsappLineFactory extends Factory
{
    protected $model = WhatsappLine::class;

    public function definition(): array
    {
        return [
            'phone'        => '91' . fake()->unique()->numerify('##########'),
            'display_name' => fake()->company(),
            'status'       => RecordStatus::Active,
            'is_default'   => false,
            'line_password' => null,
        ];
    }

    /** Line with a WABA ID (connected). */
    public function connected(): static
    {
        return $this->state(fn () => [
            'waba_id'               => 'WABA' . fake()->numerify('##########'),
            'alibaba_cust_space_id' => 'SP'   . fake()->numerify('##########'),
        ]);
    }

    /** Mark as the default line for the tenant. */
    public function defaultLine(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /** Line with a Number Access password set (plain: 'password123'). */
    public function withPassword(string $plain = 'password123'): static
    {
        return $this->state(fn () => ['line_password' => Hash::make($plain)]);
    }
}
