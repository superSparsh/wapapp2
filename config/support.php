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
            'Humara agent jaldi aapse connect karega. Aap Inbox queue mein ho.',
        ),
        'already_queued_message' => env(
            'SUPPORT_HANDOFF_ALREADY_QUEUED_MESSAGE',
            'Aap pehle se agent queue mein ho. Humara team jaldi reply karega.',
        ),
    ],
];
