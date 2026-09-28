<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Customer readiness (legacy parity)
    |--------------------------------------------------------------------------
    | Public form pages are imported separately. This config drives validation,
    | eligibility, and result redirects only.
    */

    'enforce_website_reachability' => (bool) env('CUSTOMER_READINESS_ENFORCE_WEBSITE', false),

    'calendly_url' => env(
        'CUSTOMER_READINESS_CALENDLY_URL',
        'https://calendly.com/tekpro-connect/25min'
    ),

    'setup_guide_url' => env(
        'CUSTOMER_READINESS_SETUP_GUIDE_URL',
        'https://www.youtube.com/watch?v=y76Hp7ZG9HQ&list=PL21cVCjAc0yaiw7G8qCx-e7Pi6nczeXwS&index=1'
    ),

    'login_url' => env('CUSTOMER_READINESS_LOGIN_URL', '/login'),

    'onboarding_pdf' => env(
        'CUSTOMER_READINESS_ONBOARDING_PDF',
        'Requirements List for the onboarding.pdf'
    ),

    'doc_types' => [
        'GST certificate',
        'Udyam MSME certificate',
    ],

    /** Map older stub form values → legacy docType labels. */
    'doc_type_aliases' => [
        'gst_certificate' => 'GST certificate',
        'udyam_msme_certificate' => 'Udyam MSME certificate',
        'business_license' => 'GST certificate',
        'utility_bill' => 'GST certificate',
        'other' => 'Udyam MSME certificate',
    ],

    'free_email_domains' => [
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'ymail.com',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'proton.me',
        'protonmail.com',
        'zoho.com',
        'gmx.com',
        'yandex.com',
        'rediffmail.com',
        'mail.com',
        'pm.me',
        'inbox.ru',
    ],

    'meta_portfolio_url_regex' => '/^https:\/\/business\.facebook\.com\/latest\/settings\/business_info\?business_id=\d+$/i',
];
