<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Commerce\Enums\PaymentLinkStatus;
use App\Domains\Commerce\Models\CommercePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommercePayment>
 */
class CommercePaymentFactory extends Factory
{
    protected $model = CommercePayment::class;

    public function definition(): array
    {
        return [
            'internal_order_ref'       => 'WP-' . time() . '-' . $this->faker->numberBetween(100, 999),
            'customer_name'            => $this->faker->name(),
            'customer_phone'           => '91' . $this->faker->numerify('##########'),
            'amount'                   => $this->faker->randomFloat(2, 100, 50000),
            'currency'                 => 'INR',
            'razorpay_payment_link_id' => 'plink_' . $this->faker->lexify('????????????????'),
            'payment_link'             => 'https://rzp.io/l/' . $this->faker->lexify('????????'),
            'status'                   => PaymentLinkStatus::Created,
            'razorpay_payment_id'      => null,
            'paid_at'                  => null,
            'expires_at'               => now()->addHours(24),
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status'              => PaymentLinkStatus::Paid,
            'razorpay_payment_id' => 'pay_' . $this->faker->lexify('????????????????'),
            'paid_at'             => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(['status' => PaymentLinkStatus::Sent]);
    }

    public function expired(): static
    {
        return $this->state([
            'status'     => PaymentLinkStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);
    }
}
