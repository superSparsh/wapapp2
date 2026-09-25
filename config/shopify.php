<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Shopify webhook topics (legacy access_scopes.js parity)
    |--------------------------------------------------------------------------
    |
    | Keys must match topicFromPath() output (orders/create → orders_create).
    | Product scopes require an audience (mail list) instead of order phone.
    |
    */
    'scopes' => [
        'customers_enable' => ['label' => 'Customers Enable', 'needs_mail_list' => false],
        'customers_update' => ['label' => 'Customers Update', 'needs_mail_list' => false],
        'customers_disable' => ['label' => 'Customers Disable', 'needs_mail_list' => false],
        'customers_marketing_consent_update' => ['label' => 'Customers Marketing Consent Update', 'needs_mail_list' => false],
        'orders_create' => ['label' => 'Orders Create', 'needs_mail_list' => false],
        'orders_updated' => ['label' => 'Orders Updated', 'needs_mail_list' => false],
        'orders_fulfilled' => ['label' => 'Orders Fulfilled', 'needs_mail_list' => false],
        'orders_paid' => ['label' => 'Orders Paid', 'needs_mail_list' => false],
        'orders_partially_fulfilled' => ['label' => 'Orders Partially Fulfilled', 'needs_mail_list' => false],
        'draft_orders_create' => ['label' => 'Draft Orders Create', 'needs_mail_list' => false],
        'draft_orders_update' => ['label' => 'Draft Orders Update', 'needs_mail_list' => false],
        'checkouts_create' => ['label' => 'Checkouts Create (Abandoned Cart)', 'needs_mail_list' => false],
        'checkouts_update' => ['label' => 'Checkouts Update (Abandoned Cart)', 'needs_mail_list' => false],
        'product_listings_add' => ['label' => 'Product Listings Add', 'needs_mail_list' => true],
        'product_listings_remove' => ['label' => 'Product Listings Remove', 'needs_mail_list' => true],
        'product_listings_update' => ['label' => 'Product Listings Update', 'needs_mail_list' => true],
        'products_create' => ['label' => 'Products Create', 'needs_mail_list' => true],
        'products_update' => ['label' => 'Products Update', 'needs_mail_list' => true],
        'app_uninstalled' => ['label' => 'App Uninstalled', 'needs_mail_list' => false],
    ],
];
