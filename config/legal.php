<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Legal / DPDP notice copy
    |--------------------------------------------------------------------------
    |
    | Used by logged-in Privacy Policy and Terms pages. Review with leadership
    | before publishing on login, signup, or public footer.
    |
    */

    'company_legal_name' => env('LEGAL_COMPANY_NAME', 'Tekkonnectpro IT Services'),
    'product_name' => env('LEGAL_PRODUCT_NAME', 'WapApp'),
    'company_location' => env('LEGAL_COMPANY_LOCATION', 'Bengaluru, India'),

    'grievance' => [
        'email' => env('LEGAL_GRIEVANCE_EMAIL', 'support@tittu.in'),
        'response_days' => (int) env('LEGAL_GRIEVANCE_RESPONSE_DAYS', 7),
    ],

    'processing' => [
        'primary_region' => env('LEGAL_PROCESSING_REGION', 'Singapore'),
        'whatsapp_bsp' => 'Alibaba Cloud Chat App Message Service (CAMS)',
        'platform' => 'Meta Platforms, Inc. (WhatsApp Business Platform)',
    ],

    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '29 October 2026'),
];
