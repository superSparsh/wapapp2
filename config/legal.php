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

    /*
    |--------------------------------------------------------------------------
    | Breach / incident response (DPDP s.8(6))
    |--------------------------------------------------------------------------
    |
    | Internal playbook contacts. Keep owner + escalation current.
    | Not linked from sidebar/footer until leadership publishes.
    |
    */

    'breach' => [
        'owner_name' => env('LEGAL_BREACH_OWNER_NAME', 'Privacy / Security Owner'),
        'owner_email' => env('LEGAL_BREACH_OWNER_EMAIL', 'support@tittu.in'),
        'escalation_email' => env('LEGAL_BREACH_ESCALATION_EMAIL', 'support@tittu.in'),
        'board_notify_hours' => (int) env('LEGAL_BREACH_BOARD_HOURS', 72),
        'playbook_version' => env('LEGAL_BREACH_PLAYBOOK_VERSION', '1.0'),
        'playbook_date' => env('LEGAL_BREACH_PLAYBOOK_DATE', '29 October 2026'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta / WhatsApp policy documents (signup acknowledgements)
    |--------------------------------------------------------------------------
    */

    'meta_policies' => [
        'business_terms' => [
            'label' => 'WhatsApp Business Terms of Service',
            'url' => env('LEGAL_META_BUSINESS_TERMS_URL', 'https://www.whatsapp.com/legal/business-terms'),
            'pdf_url' => env('LEGAL_META_BUSINESS_TERMS_PDF_URL', 'https://www.whatsapp.com/legal/business-terms'),
        ],
        'commerce_policy' => [
            'label' => 'WhatsApp Commerce Policy',
            'url' => env('LEGAL_META_COMMERCE_POLICY_URL', 'https://www.whatsapp.com/legal/commerce-policy'),
            'pdf_url' => env('LEGAL_META_COMMERCE_POLICY_PDF_URL', 'https://www.whatsapp.com/legal/commerce-policy'),
        ],
        'business_policy' => [
            'label' => 'WhatsApp Business Policy',
            'url' => env('LEGAL_META_BUSINESS_POLICY_URL', 'https://www.whatsapp.com/legal/business-policy'),
            'pdf_url' => env('LEGAL_META_BUSINESS_POLICY_PDF_URL', 'https://www.whatsapp.com/legal/business-policy'),
        ],
        'program_addendum' => [
            'label' => 'WhatsApp Business Solution Provider Program Addendum',
            'url' => env(
                'LEGAL_META_PROGRAM_ADDENDUM_URL',
                'https://www.whatsapp.com/legal/business-solution-provider-terms-addendum'
            ),
            'pdf_url' => env(
                'LEGAL_META_PROGRAM_ADDENDUM_PDF_URL',
                'https://www.whatsapp.com/legal/business-solution-provider-terms-addendum'
            ),
        ],
    ],

    'custom_terms' => [
        'max_chars' => (int) env('LEGAL_CUSTOM_TERMS_MAX_CHARS', 50000),
        'title_max' => 191,
    ],
];
