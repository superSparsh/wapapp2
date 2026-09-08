<?php

namespace Database\Factories;

use App\Models\TriggerVariable;
use App\Models\WhatsappLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TriggerVariable>
 */
class TriggerVariableFactory extends Factory
{
    protected $model = TriggerVariable::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'variable_name' => $name,
            'template_code' => $name.'_template',
            'template_name' => ucfirst(str_replace('_', ' ', $name)),
            'whatsapp_line_id' => null,
            'list_id' => null,
            'list_name' => null,
        ];
    }

    public function forLine(WhatsappLine $line): static
    {
        return $this->state(fn (): array => [
            'whatsapp_line_id' => $line->id,
        ]);
    }

    public function anyMessage(): static
    {
        return $this->state(fn (): array => [
            'variable_name' => config('trigger-template.any_message_trigger'),
        ]);
    }
}
