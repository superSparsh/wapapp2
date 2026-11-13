<?php

declare(strict_types=1);

return [
    'per_page' => (int) env('TEAM_MEMBERS_PER_PAGE', 10),

    'permissions' => [
        'inbox_read' => 'Inbox',
        'template_read' => 'Templates',
        'audience_read' => 'Audience',
        'campaign_read' => 'Campaigns',
        'chatbot_read' => 'Chat bot',
        'drip_read' => 'Drip Marketing',
        'whatsapp_flow_read' => 'WhatsApp Flows',
        'form_builder_read' => 'Form builder',
        'commerce_read' => 'Commerce',
        'integration_read' => 'Connected Apps',
    ],

    'default_permissions' => [
        'inbox_read' => false,
        'template_read' => false,
        'audience_read' => false,
        'campaign_read' => false,
        'chatbot_read' => false,
        'drip_read' => false,
        'whatsapp_flow_read' => false,
        'form_builder_read' => false,
        'commerce_read' => false,
        'integration_read' => false,
    ],

    'landing_priority' => [
        'inbox_read' => 'inbox.index',
        'chatbot_read' => 'chatbot.index',
        'drip_read' => 'automation.drip.index',
        'whatsapp_flow_read' => 'whatsapp-flows.index',
        'template_read' => 'templates.index',
        'audience_read' => 'audience.index',
        'campaign_read' => 'campaigns.index',
        'form_builder_read' => 'form-builder.index',
        'commerce_read' => 'commerce.index',
        'integration_read' => 'integration.index',
    ],

    'route_permissions' => [
        'inbox.index' => 'inbox_read',
        'templates.index' => 'template_read',
        'templates.variables' => 'template_read',
        'audience.index' => 'audience_read',
        'campaigns.index' => 'campaign_read',
        'chatbot.index' => 'chatbot_read',
        'automation.drip.index' => 'drip_read',
        'whatsapp-flows.index' => 'whatsapp_flow_read',
        'form-builder.index' => 'form_builder_read',
        'commerce.index' => 'commerce_read',
        'commerce.orders' => 'commerce_read',
        'commerce.settings' => 'commerce_read',
        'commerce.catalog' => 'commerce_read',
        'commerce.products' => 'commerce_read',
        'integration.index' => 'integration_read',
        'integration.calendly' => 'integration_read',
        'integration.google-calendar' => 'integration_read',
    ],

    'import' => [
        'max_rows' => 500,
        'max_size_kb' => 5120,
    ],

    'impersonation_session_key' => 'team_impersonator_id',
    'impersonation_owner_session_key' => 'team_impersonator_owner_id',
];
