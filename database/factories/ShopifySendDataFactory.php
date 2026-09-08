<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ThirdParty\Models\ShopifySendData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopifySendData>
 */
class ShopifySendDataFactory extends Factory
{
    protected $model = ShopifySendData::class;

    public function definition(): array
    {
        return [
            'user_id'         => 1,
            'event_type'      => $this->faker->randomElement(['products_create', 'products_update', 'products_delete']),
            'payload'         => ['product_id' => $this->faker->numberBetween(1000, 9999)],
            'whatsapp_number' => '91' . $this->faker->numerify('##########'),
            'status'          => 'sent',
            'sent_at'         => now(),
        ];
    }
}
