<?php

declare(strict_types=1);

return [
    [
        'label' => 'Dashboard',
        'route' => 'admin.dashboard',
        'icon' => 'smart-home',
        'matches' => [],
    ],
    [
        'label' => 'Customer',
        'route' => 'admin.customers.index',
        'icon' => 'users',
        'matches' => ['admin.customers.*'],
        'children' => [
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'matches' => ['admin.customers.show', 'admin.customers.edit']],
            ['label' => 'Message performance', 'route' => 'admin.message-performance.index', 'matches' => []],
            ['label' => 'Retention & Renewals', 'route' => 'admin.retention.index', 'matches' => []],
            ['label' => 'Wallet Recharges', 'route' => 'admin.wallet-recharges.index', 'matches' => []],
            ['label' => 'Alibaba CAMS bills', 'route' => 'admin.cloud-bills.index', 'matches' => []],
            ['label' => 'Billing audit', 'route' => 'admin.billing-audit.index', 'matches' => []],
            ['label' => 'Expired data purge', 'route' => 'admin.data-purge.index', 'matches' => []],
            ['label' => 'WhatsApp Health', 'route' => 'admin.whatsapp-health.index', 'matches' => []],
        ],
    ],
    [
        'label' => 'Plan',
        'route' => 'admin.plans.index',
        'icon' => 'ticket',
        'matches' => ['admin.plans.*'],
        'children' => [
            ['label' => 'Plans', 'route' => 'admin.plans.index', 'matches' => ['admin.plans.show', 'admin.plans.edit', 'admin.plans.create']],
            ['label' => 'Currencies', 'route' => 'admin.currencies.index', 'matches' => ['admin.currencies.create', 'admin.currencies.edit']],
            ['label' => 'Tax settings', 'route' => 'admin.tax.edit', 'matches' => []],
            ['label' => 'Invoice template', 'route' => 'admin.invoice-template.edit', 'matches' => ['admin.invoice-template.preview']],
            ['label' => 'Country pricing', 'route' => 'admin.pricing.index', 'matches' => []],
            ['label' => 'Razorpay subscriptions', 'route' => 'admin.razorpay.index', 'matches' => []],
        ],
    ],
    [
        'label' => 'Admin',
        'route' => 'admin.admins.index',
        'icon' => 'box',
        'matches' => ['admin.admins.*'],
        'children' => [
            ['label' => 'Admins', 'route' => 'admin.admins.index', 'matches' => ['admin.admins.create', 'admin.admins.edit']],
            ['label' => 'Admin groups', 'route' => 'admin.admin-roles.index', 'matches' => ['admin.admin-roles.create', 'admin.admin-roles.edit']],
        ],
    ],
    [
        'label' => 'Settings',
        'route' => 'admin.settings.index',
        'icon' => 'star',
        'matches' => ['admin.settings.*'],
        'children' => [
            ['label' => 'All settings', 'route' => 'admin.settings.index', 'matches' => []],
            ['label' => 'Payment gateways', 'route' => 'admin.payment-gateways.edit', 'matches' => []],
            ['label' => 'OAuth', 'route' => 'admin.oauth.edit', 'matches' => []],
            ['label' => 'Template gallery', 'route' => 'admin.platform-templates.index', 'matches' => ['admin.platform-templates.create', 'admin.platform-templates.edit']],
            ['label' => 'Form templates', 'route' => 'admin.form-templates.index', 'matches' => ['admin.form-templates.create', 'admin.form-templates.edit']],
            ['label' => 'Page layouts', 'route' => 'admin.page-layouts.index', 'matches' => ['admin.page-layouts.create', 'admin.page-layouts.edit']],
            ['label' => 'Languages', 'route' => 'admin.languages.index', 'matches' => ['admin.languages.create', 'admin.languages.edit']],
            ['label' => 'Queues', 'route' => 'admin.queues.index', 'matches' => ['admin.queues.*']],
        ],
    ],
    [
        'label' => 'Announcement',
        'route' => 'admin.announcements.index',
        'icon' => 'file-text',
        'matches' => [],
    ],
    [
        'label' => 'Submissions',
        'route' => 'admin.submissions.index',
        'icon' => 'ticket',
        'matches' => ['admin.submissions.*'],
    ],
    [
        'label' => 'Plugins',
        'route' => 'admin.plugins.index',
        'icon' => 'box',
        'matches' => [],
    ],
];
