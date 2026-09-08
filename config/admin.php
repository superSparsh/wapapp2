<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin View allowlist
    |--------------------------------------------------------------------------
    |
    | Admin View menu item is shown ONLY when the signed-in customer email is:
    | - an active row in the central `admins` table, OR
    | - listed here (ADMIN_VIEW_EMAILS in .env)
    |
    | Example: ADMIN_VIEW_EMAILS=sparsh@tittu.in,ops@tittu.in
    |
    */
    'view_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_VIEW_EMAILS', '')),
    ))),
];
