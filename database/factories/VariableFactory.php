<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Enums\VariableType;
use App\Models\Variable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Variable>
 */
class VariableFactory extends Factory
{
    protected $model = Variable::class;

    public function definition(): array
    {
        return [
            'type' => VariableType::Dynamic,
            'name' => Str::snake($this->faker->unique()->words(2, true)),
            'data_type' => VariableDataType::String,
            'value' => null,
            'whatsapp_line_id' => null,
            'team_member_id' => null,
            'team_member_name' => null,
        ];
    }

    public function forTeamMember(int $memberId, ?string $memberName = null): static
    {
        return $this->state(fn (): array => [
            'team_member_id' => $memberId,
            'team_member_name' => $memberName ?? 'Team Member',
        ]);
    }

    public function media(VariableDataType $dataType): static
    {
        return $this->state(fn (): array => [
            'data_type' => $dataType,
            'value' => 'template-variables/sample.pdf',
        ]);
    }
}
