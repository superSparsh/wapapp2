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
        'label' => 'Customers',
        'route' => 'admin.customers.index',
        'icon' => 'users',
        'matches' => ['admin.customers.*'],
        'children' => [
            ['label' => 'All customers', 'route' => 'admin.customers.index', 'matches' => ['admin.customers.show', 'admin.customers.edit']],
            ['label' => 'Retention', 'route' => 'admin.retention.index', 'matches' => []],
            ['label' => 'Billing audit', 'route' => 'admin.billing-audit.index', 'matches' => []],
            ['label' => 'WhatsApp Health', 'route' => 'admin.whatsapp-health.index', 'matches' => []],
            ['label' => 'Message performance', 'route' => 'admin.message-performance.index', 'matches' => []],
            ['label' => 'Wallet recharges', 'route' => 'admin.wallet-recharges.index', 'matches' => []],
            ['label' => 'Renew requests', 'route' => 'admin.renew-requests.index', 'matches' => []],
            ['label' => 'Recharge requests', 'route' => 'admin.recharge-requests.index', 'matches' => []],
            ['label' => 'Submissions', 'route' => 'admin.submissions.index', 'matches' => ['admin.submissions.readiness.show', 'admin.submissions.onboarding.show']],
            ['label' => 'Zoho/Razorpay credits', 'route' => 'admin.zoho-credits.index', 'matches' => ['admin.zoho-credits.show']],
            ['label' => 'Cloud bills', 'route' => 'admin.cloud-bills.index', 'matches' => []],
            ['label' => 'Data purge', 'route' => 'admin.data-purge.index', 'matches' => []],
        ],
    ],
    [
        'label' => 'Plans & Pricing',
        'route' => 'admin.plans.index',
        'icon' => 'ticket',
        'matches' => ['admin.plans.*'],
        'children' => [
            ['label' => 'Plans', 'route' => 'admin.plans.index', 'matches' => ['admin.plans.show', 'admin.plans.edit', 'admin.plans.create']],
            ['label' => 'Country pricing', 'route' => 'admin.pricing.index', 'matches' => []],
            ['label' => 'Razorpay subscriptions', 'route' => 'admin.razorpay.index', 'matches' => []],
            ['label' => 'Currencies', 'route' => 'admin.currencies.index', 'matches' => ['admin.currencies.create', 'admin.currencies.edit']],
            ['label' => 'Tax settings', 'route' => 'admin.tax.edit', 'matches' => []],
            ['label' => 'Invoice template', 'route' => 'admin.invoice-template.edit', 'matches' => ['admin.invoice-template.preview']],
            ['label' => 'Payment gateways', 'route' => 'admin.payment-gateways.edit', 'matches' => []],
        ],
    ],
    [
        'label' => 'Admins',
        'route' => 'admin.admins.index',
        'icon' => 'box',
        'matches' => ['admin.admins.*'],
        'children' => [
            ['label' => 'All admins', 'route' => 'admin.admins.index', 'matches' => ['admin.admins.create', 'admin.admins.edit']],
            ['label' => 'Roles', 'route' => 'admin.admin-roles.index', 'matches' => ['admin.admin-roles.create', 'admin.admin-roles.edit']],
        ],
    ],
    [
        'label' => 'Queues',
        'route' => 'admin.queues.index',
        'icon' => 'ticket',
        'matches' => ['admin.queues.*'],
    ],
    [
        'label' => 'Announcements',
        'route' => 'admin.announcements.index',
        'icon' => 'file-text',
        'matches' => [],
    ],
    [
        'label' => 'Settings',
        'route' => 'admin.settings.index',
        'icon' => 'star',
        'matches' => ['admin.settings.*'],
        'children' => [
            ['label' => 'General', 'route' => 'admin.settings.index', 'matches' => []],
            ['label' => 'OAuth', 'route' => 'admin.oauth.edit', 'matches' => []],
            ['label' => 'Template gallery', 'route' => 'admin.platform-templates.index', 'matches' => ['admin.platform-templates.create', 'admin.platform-templates.edit']],
            ['label' => 'Form templates', 'route' => 'admin.form-templates.index', 'matches' => ['admin.form-templates.create', 'admin.form-templates.edit']],
            ['label' => 'Page layouts', 'route' => 'admin.page-layouts.index', 'matches' => ['admin.page-layouts.create', 'admin.page-layouts.edit']],
            ['label' => 'Languages', 'route' => 'admin.languages.index', 'matches' => ['admin.languages.create', 'admin.languages.edit']],
            ['label' => 'Plugins', 'route' => 'admin.plugins.index', 'matches' => []],
        ],
    ],
];
