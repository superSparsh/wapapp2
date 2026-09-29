<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Forms → WapApp onboarding API
    |--------------------------------------------------------------------------
    |
    | Consumed by forms.tekprocloud.com (WHATSAPP_AUTOMATION_API_URL).
    | Legacy path: POST /api/forms/onboarding
    |
    */
    'login_url' => env('FORMS_ONBOARDING_LOGIN_URL', env('APP_URL', 'http://localhost').'/login'),
];
