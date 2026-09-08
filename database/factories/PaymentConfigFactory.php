<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Commerce\Models\PaymentConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentConfig>
 */
class PaymentConfigFactory extends Factory
{
    protected $model = PaymentConfig::class;

    public function definition(): array
    {
        return [
            'client_name'              => $this->faker->company(),
            'razorpay_key'             => 'rzp_test_' . $this->faker->lexify('????????????????'),
            'razorpay_secret'          => $this->faker->lexify('????????????????????????????????'),
            'payment_template_id'      => null,
            'confirmation_template_id' => null,
        ];
    }
}
