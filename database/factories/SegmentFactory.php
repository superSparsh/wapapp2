<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Audience\Models\Segment;
use App\Models\MailList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Segment>
 */
class SegmentFactory extends Factory
{
    protected $model = Segment::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'mail_list_id' => MailList::factory(),
            'conditions' => [
                ['field' => 'status', 'type' => 'equals', 'value' => 'subscribed'],
            ],
            'contact_count' => 0,
        ];
    }
}
