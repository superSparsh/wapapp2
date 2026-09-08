<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        return [
            'parent_user_id' => 1,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '91'.fake()->unique()->numerify('##########'),
            'password' => Hash::make('password'),
            'role' => TeamMemberRole::Member,
            'status' => RecordStatus::Active,
            'permissions' => config('team.default_permissions'),
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (): array => [
            'role' => TeamMemberRole::Manager,
        ]);
    }
}
