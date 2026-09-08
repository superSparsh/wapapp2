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
        ],
    ],
    [
        'label' => 'Admins',
        'route' => 'admin.admins.index',
        'icon' => 'box',
        'matches' => ['admin.admins.*'],
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
    ],
];
