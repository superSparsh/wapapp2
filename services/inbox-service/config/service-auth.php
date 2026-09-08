<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Service-to-Service Authentication
    |--------------------------------------------------------------------------
    |
    | Shared secret tokens used to authenticate requests between the monolith
    | and the inbox microservice.
    |
    */
    'token' => env('INBOX_SERVICE_TOKEN', env('SERVICE_AUTH_TOKEN', 'default-inbox-service-secret-token')),
    'header_name' => env('SERVICE_AUTH_HEADER', 'X-Service-Token'),
    'tenant_header' => 'X-Tenant-Id',
    'actor_user_id_header' => 'X-Actor-User-Id',
    'actor_team_member_id_header' => 'X-Actor-Team-Member-Id',
    'actor_user_uuid_header' => 'X-Actor-User-Uuid',
    'actor_team_member_uuid_header' => 'X-Actor-Team-Member-Uuid',
    'actor_is_team_member_header' => 'X-Actor-Is-Team-Member',
    'actor_assigned_lines_header' => 'X-Actor-Assigned-Lines',
    'actor_phone_masking_header' => 'X-Actor-Phone-Masking',
];
