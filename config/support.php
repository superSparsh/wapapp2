<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Company support bot handoff
    |--------------------------------------------------------------------------
    |
    | Used by SupportHandoffService / handoff_to_inbox chatbot function when
    | escalating a WhatsApp chat to a human agent (Inbox today).
    |
    */
    'handoff' => [
        'confirmation_message' => env(
            'SUPPORT_HANDOFF_MESSAGE',
            'Thank you. Please wait while we connect you with our expert. An agent typically replies within 15–30 minutes during Monday–Friday, 10:00 AM – 6:00 PM IST.',
        ),
        'already_queued_message' => env(
            'SUPPORT_HANDOFF_ALREADY_QUEUED_MESSAGE',
            'You are already in our agent queue. Please wait — an expert typically replies within 15–30 minutes during Monday–Friday, 10:00 AM – 6:00 PM IST.',
        ),
    ],
];
