<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Domains\Commerce\Models\CommerceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommerceOrder>
 */
class CommerceOrderFactory extends Factory
{
    protected $model = CommerceOrder::class;

    public function definition(): array
    {
        return [
            'catalog_id'          => $this->faker->numerify('##############'),
            'customer_name'       => $this->faker->name(),
            'customer_phone'      => '91' . $this->faker->numerify('##########'),
            'product_items'       => [
                [
                    'product_retailer_id' => $this->faker->numerify('#####'),
                    'quantity'            => $this->faker->numberBetween(1, 5),
                    'item_price'          => $this->faker->randomFloat(2, 100, 5000),
                ],
            ],
            'total_price'         => $this->faker->randomFloat(2, 100, 50000),
            'currency'            => 'INR',
            'order_status'        => OrderStatus::New,
            'payment_status'      => PaymentStatus::Pending,
            'whatsapp_line_id'    => null,
            'external_message_id' => $this->faker->optional()->lexify('wamid.???????????????????'),
            'payment_link'        => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'payment_status' => PaymentStatus::Paid,
            'payment_link'   => 'https://rzp.io/l/' . $this->faker->lexify('????????'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['order_status' => OrderStatus::Cancelled]);
    }

    public function delivered(): static
    {
        return $this->state([
            'order_status'   => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
        ]);
    }
}
