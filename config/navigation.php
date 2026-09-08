<?php

return [
    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'smart-home', 'matches' => ['dashboard.analytics', 'dashboard.overview', 'dashboard.campaigns', 'dashboard.wallet']],
    ['label' => 'Inbox', 'route' => 'inbox.index', 'icon' => 'users', 'badge' => null, 'matches' => ['inbox.show', 'inbox.empty', 'inbox.modals.ask-for-address', 'inbox.modals.send-contact']],
    [
        'label' => 'Automation',
        'route' => 'chatbot.index',
        'icon' => 'users',
        'matches' => ['automation.index'],
        'children' => [
            ['label' => 'Chat bot', 'route' => 'chatbot.index', 'active_key' => 'automation.chatbot', 'matches' => ['chatbot.*']],
            ['label' => 'Drip Marketing', 'route' => 'automation.drip.index', 'active_key' => 'automation.drip.index', 'matches' => ['automation.drip.*']],
            ['label' => 'Flows', 'route' => 'whatsapp-flows.index', 'active_key' => 'automation.whatsapp-flows', 'matches' => ['whatsapp-flows.*', 'automation.flows']],
        ],
    ],
    [
        'label' => 'Templates Design',
        'route' => 'templates.index',
        'icon' => 'ticket',
        'children' => [
            ['label' => 'Variables', 'route' => 'templates.variables', 'matches' => ['templates.variables.create', 'templates.variables.samples']],
            ['label' => 'Templates', 'route' => 'templates.index', 'matches' => ['templates.preview', 'templates.builder.create', 'templates.builder.body', 'templates.builder.header', 'templates.builder.body-media', 'templates.builder.footer', 'templates.builder.buttons', 'templates.builder.submit']],
        ],
    ],
    ['label' => 'Audience', 'route' => 'audience.index', 'icon' => 'box', 'matches' => ['audience.overview', 'audience.subscribers', 'audience.subscribers.empty', 'audience.subscribers.detail', 'audience.subscribers.import', 'audience.segments', 'audience.forms', 'audience.list-fields', 'audience.settings']],
    ['label' => 'Campaigns', 'route' => 'campaigns.index', 'icon' => 'file-text', 'matches' => ['campaigns.active', 'campaigns.scheduled', 'campaigns.detail', 'campaigns.create.step-1', 'campaigns.create.step-2', 'campaigns.create.step-3', 'campaigns.create.step-4', 'campaigns.create.step-5', 'campaigns.create.step-6']],
    [
        'label' => 'Connected Apps',
        'route' => 'integration.index',
        'icon' => 'star',
        'children' => [
            ['label' => 'Shopify', 'route' => 'integration.index', 'matches' => ['integration.shopify.scopes']],
            ['label' => 'Calendly', 'route' => 'integration.calendly', 'matches' => ['integration.calendly.connected', 'integration.calendly.events']],
            ['label' => 'Google Calendar', 'route' => 'integration.google-calendar', 'matches' => []],
        ],
    ],
    ['label' => 'Form builder', 'route' => 'form-builder.index', 'icon' => 'star', 'matches' => ['form-builder.create', 'form-builder.edit']],
    [
        'label' => 'Commerce',
        'route' => 'commerce.index',
        'icon' => 'star',
        'children' => [
            ['label' => 'Catalogs', 'route' => 'commerce.index', 'matches' => ['commerce.catalog']],
            ['label' => 'Orders', 'route' => 'commerce.orders', 'matches' => ['commerce.orders.detail']],
            ['label' => 'Payments', 'route' => 'commerce.settings', 'matches' => ['commerce.products', 'commerce.product-detail']],
        ],
    ],
    [
        'label' => 'Account',
        'route' => 'profile.index',
        'icon' => 'box',
        'children' => [
            ['label' => 'My profile', 'route' => 'profile.index'],
            ['label' => 'Security', 'route' => 'profile.security'],
            ['label' => 'Integration', 'route' => 'profile.integration', 'matches' => ['profile.integration.connected']],
            ['label' => 'Manage Phone Numbers', 'route' => 'profile.phone-lines.index', 'matches' => ['profile.phone-lines.create', 'profile.phone-lines.edit']],
            ['label' => 'API', 'route' => 'profile.api', 'matches' => ['profile.api.docs']],
            ['label' => 'Subscription', 'route' => 'profile.subscription', 'matches' => ['profile.subscription.billing', 'profile.subscription.upgrade', 'profile.subscription.manage', 'profile.subscription.payment']],
            ['label' => 'Business Alerts Setup', 'route' => 'profile.alerts'],
            ['label' => 'Data Deletion', 'route' => 'profile.data-deletion'],
            ['label' => 'Activity Logs', 'route' => 'profile.activity-logs'],
        ],
    ],
];
