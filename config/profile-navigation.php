<?php

return [
    ['route' => 'profile.index', 'label' => 'My profile'],
    ['route' => 'profile.security', 'label' => 'Security'],
    ['route' => 'profile.integration', 'label' => 'Third Party Integration', 'matches' => ['profile.integration.connected']],
    ['route' => 'profile.phone-lines.index', 'label' => 'Manage Phone Numbers'],
    ['route' => 'profile.api', 'label' => 'API', 'matches' => ['profile.api.docs']],
    ['route' => 'profile.subscription', 'label' => 'Subscription', 'matches' => ['profile.subscription.billing', 'profile.subscription.upgrade', 'profile.subscription.manage', 'profile.subscription.payment']],
    ['route' => 'profile.alerts', 'label' => 'Business Alerts Setup'],
    ['route' => 'profile.data-deletion', 'label' => 'Data Deletion'],
    ['route' => 'profile.activity-logs', 'label' => 'Activity Logs'],
];
