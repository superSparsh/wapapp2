<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Models;

use App\Models\TenantModel;
use Database\Factories\PaymentConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentConfig extends TenantModel
{
    use HasFactory;

    protected $table = 'commerce_payment_configs';

    /** @return \Illuminate\Database\Eloquent\Factories\Factory<static> */
    protected static function newFactory(): PaymentConfigFactory
    {
        return PaymentConfigFactory::new();
    }

    protected $fillable = [
        'client_name',
        'razorpay_key',
        'razorpay_secret',
        'payment_template_id',
        'confirmation_template_id',
    ];

    /** @var list<string> */
    protected $hidden = ['razorpay_secret'];

    protected function casts(): array
    {
        return [
            'razorpay_secret'         => 'encrypted',
            'payment_template_id'     => 'integer',
            'confirmation_template_id'=> 'integer',
        ];
    }

    public function isConfigured(): bool
    {
        return filled($this->razorpay_key) && filled($this->razorpay_secret);
    }
}
