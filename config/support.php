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
            'Thank you. An agent will connect with you shortly. You are now in the support queue.',
        ),
        'already_queued_message' => env(
            'SUPPORT_HANDOFF_ALREADY_QUEUED_MESSAGE',
            'You are already in the support queue. Our team will reply shortly.',
        ),
    ],
];
