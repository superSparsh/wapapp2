<?php

declare(strict_types=1);

/**
 * Number-specific access mode ("Login as this number" / public line-login).
 *
 * Operators get a full working dashboard for that line (Inbox, campaigns,
 * templates, automation, AI, webhooks, …). Account security / billing /
 * WABA admin stays on the main owner session only.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Blocked route name prefixes (Laravel routeIs patterns)
    |--------------------------------------------------------------------------
    */
    'blocked_route_patterns' => [
        'profile.security*',
        'profile.api*',
        'profile.subscription*',
        'profile.alerts*',
        'profile.activity-logs*',
        'profile.data-deletion*',
        'profile.integration*',
        'profile.phone-lines.create',
        'profile.phone-lines.store',
        'profile.phone-lines.password',
        'profile.phone-lines.login-as',
        'profile.phone-lines.set-default',
        'profile.phone-lines.add*',
        'profile.index',
        'profile.update',
        'my-team.*',
        'manager.*',
        'admin.*',
        'integration.*',
        'form-builder.*',
        'commerce.*',
        'onboarding.*',
        'subscription.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Always allowed while locked (even if they look "account-ish")
    |--------------------------------------------------------------------------
    */
    'allowed_route_patterns' => [
        'dashboard*',
        'inbox.*',
        'chatbot.*',
        'automation.*',
        'whatsapp-flows.*',
        'templates.*',
        'audience.*',
        'campaigns.*',
        'openai-key.*',
        'trigger-template.*',
        'webhooks.*',
        'tutorials.*',
        'faqs.*',
        'announcements.*',
        'logout',
        'profile.phone-lines.exit-context',
        'profile.phone-lines.index',
        'global-search*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar: hide these top-level items while locked
    |--------------------------------------------------------------------------
    */
    'hidden_nav_routes' => [
        'integration.index',
        'form-builder.index',
        'commerce.index',
        'profile.index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra sidebar links inject while locked (ops tools usually in user panel)
    |--------------------------------------------------------------------------
    */
    'extra_nav_items' => [
        [
            'label' => 'AI Assistant',
            'route' => 'openai-key.index',
            'icon' => 'star',
            'matches' => ['openai-key.*'],
        ],
        [
            'label' => 'Trigger Template',
            'route' => 'trigger-template.index',
            'icon' => 'ticket',
            'matches' => ['trigger-template.*'],
        ],
        [
            'label' => 'Webhooks',
            'route' => 'webhooks.index',
            'icon' => 'box',
            'matches' => ['webhooks.*'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User-panel links to keep while locked
    |--------------------------------------------------------------------------
    */
    'allowed_user_panel_routes' => [
        'openai-key.index',
        'trigger-template.index',
        'webhooks.index',
        'tutorials.index',
        'faqs.index',
    ],
];
