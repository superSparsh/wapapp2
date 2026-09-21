<?php

declare(strict_types=1);

namespace App\Models;

class IsvTermsAcceptance extends TenantModel
{
    protected $fillable = [
        'business_name',
        'website_email',
        'bm_id',
        'use_case',
        'business_address',
        'country_code',
        'cus_space_id',
        'status',
    ];
}
