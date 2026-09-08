<?php

/*
|--------------------------------------------------------------------------
| Feature Flags
|--------------------------------------------------------------------------
|
| Define feature flags for gradual rollouts and A/B testing.
| Each flag has a default state and description.
|
| Override at runtime:
|   app(FeatureFlag::class)->enable('chatbot.enhanced-conditions');
|
| Override via .env:
|   FEATURE_CHATBOT_ENHANCED_CONDITIONS=true
|
*/

return [

    // ── Chatbot ──
    'chatbot' => [
        'enhanced-conditions' => [
            'default' => false,
            'description' => 'Enable enhanced condition node with regex, starts_with, ends_with operators',
        ],
        'carousel-template' => [
            'default' => false,
            'description' => 'Enable carousel template node type',
        ],
        'whatsapp-flow-template' => [
            'default' => false,
            'description' => 'Enable WhatsApp Flow template node in chatbot builder',
        ],
        'function-call' => [
            'default' => false,
            'description' => 'Enable function call node for custom logic execution',
        ],
    ],

    // ── Messaging ──
    'messaging' => [
        'ai-suggestions' => [
            'default' => false,
            'description' => 'AI-powered reply suggestions in inbox',
        ],
        'bulk-import' => [
            'default' => true,
            'description' => 'Bulk contact import via CSV',
        ],
    ],

    // ── Campaigns ──
    'campaigns' => [
        'ab-testing' => [
            'default' => false,
            'description' => 'A/B testing for campaign messages',
        ],
        'scheduling' => [
            'default' => true,
            'description' => 'Schedule campaigns for future delivery',
        ],
    ],

    // ── Platform ──
    'platform' => [
        'multi-language' => [
            'default' => false,
            'description' => 'Multi-language support for the platform UI',
        ],
        'white-label' => [
            'default' => false,
            'description' => 'White-label branding per tenant',
        ],
    ],
];
