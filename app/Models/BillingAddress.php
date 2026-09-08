<?php

declare(strict_types=1);

namespace App\Models;

class BillingAddress extends TenantModel
{
    protected $fillable = [
        'gst_treatment',
        'company_name',
        'pan',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
