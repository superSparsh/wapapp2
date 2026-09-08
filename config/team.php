<?php

declare(strict_types=1);

return [
    'per_page' => (int) env('TEAM_MEMBERS_PER_PAGE', 10),

    'permissions' => [
        'template_read' => 'Templates',
        'audience_read' => 'Audience',
        'campaign_read' => 'Campaigns',
        'inbox_read' => 'Inbox',
    ],

    'default_permissions' => [
        'template_read' => false,
        'audience_read' => false,
        'campaign_read' => false,
        'inbox_read' => false,
    ],

    'landing_priority' => [
        'inbox_read' => 'inbox.index',
        'template_read' => 'templates.index',
        'audience_read' => 'audience.index',
        'campaign_read' => 'campaigns.index',
    ],

    'route_permissions' => [
        'inbox.index' => 'inbox_read',
        'templates.index' => 'template_read',
        'templates.variables' => 'template_read',
        'audience.index' => 'audience_read',
        'campaigns.index' => 'campaign_read',
    ],

    'import' => [
        'max_rows' => 500,
        'max_size_kb' => 5120,
    ],

    'impersonation_session_key' => 'team_impersonator_id',
];
