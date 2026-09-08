<?php

declare(strict_types=1);

return [
    'token' => env('CAMPAIGN_SERVICE_TOKEN', env('SERVICE_AUTH_TOKEN', 'default-campaign-service-secret-token')),
    'header' => env('SERVICE_AUTH_HEADER', 'X-Service-Token'),
    'tenant_header' => env('SERVICE_TENANT_HEADER', 'X-Tenant-Id'),
    'actor_user_id_header' => 'X-Actor-User-Id',
    'actor_team_member_id_header' => 'X-Actor-Team-Member-Id',
    'actor_user_uuid_header' => 'X-Actor-User-Uuid',
    'actor_team_member_uuid_header' => 'X-Actor-Team-Member-Uuid',
    'actor_user_name_header' => 'X-Actor-User-Name',
    'actor_team_member_name_header' => 'X-Actor-Team-Member-Name',
    'actor_is_team_member_header' => 'X-Actor-Is-Team-Member',
    'correlation_id_header' => 'X-Correlation-Id',
];
