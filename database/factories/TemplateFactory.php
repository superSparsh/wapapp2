<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        $name = Str::snake($this->faker->unique()->words(3, true));

        return [
            'code' => $name,
            'name' => ucwords(str_replace('_', ' ', $name)),
            'language' => 'en_GB',
            'category' => 'MARKETING',
            'status' => TemplateStatus::Approved,
            'source' => TemplateSource::Cams,
            'payload' => Template::defaultPayload(),
            'body_preview' => 'Hello {{first_name}}, thanks for reaching out.',
            'synced_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'code' => null,
            'status' => TemplateStatus::Draft,
            'source' => TemplateSource::Local,
            'synced_at' => null,
            'body_preview' => null,
        ]);
    }
}
