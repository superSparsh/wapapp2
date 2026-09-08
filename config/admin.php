<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin View allowlist (legacy parity)
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails that should always see "Admin View" in the tenant
    | account menu, in addition to any active row in the central `admins` table
    | with a matching email. Example: ADMIN_VIEW_EMAILS=sparsh@tittu.in,ops@tittu.in
    |
    */
    'view_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_VIEW_EMAILS', '')),
    ))),
];
