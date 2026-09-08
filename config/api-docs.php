<?php

declare(strict_types=1);

return [

    'sections' => [

        [
            'title' => 'Authentication',
            'functions' => [
                [
                    'method' => 'POST',
                    'uri' => '/api/v1/login-token',
                    'description' => 'Generate a one-time login token.',
                    'help' => 'The user can log in by visiting: <code>{app_url}/autologin/{token}</code>',
                    'parameters' => [],
                    'returns' => 'Token string in JSON.',
                    'example' => 'curl -X POST -H "accept:application/json" "{base_url}/login-token" -d "api_token={api_token}"',
                ],
            ],
        ],

        [
            'title' => 'Send to Single Subscriber',
            'functions' => [
                [
                    'method' => 'POST',
                    'uri' => '/api/v1/directmessage',
                    'description' => 'Send a template message to a single WhatsApp number.',
                    'parameters' => [
                        ['name' => 'template_uid', 'description' => 'Approved template UID.'],
                        ['name' => 'to', 'description' => 'Recipient phone number with country code, without +.'],
                        ['name' => 'from', 'description' => 'Sender WhatsApp number registered on your account.', 'optional' => true],
                        ['name' => 'first_name', 'description' => 'Recipient first name.', 'optional' => true],
                        ['name' => 'last_name', 'description' => 'Recipient last name.', 'optional' => true],
                        ['name' => 'variable_name', 'description' => 'Template variable key/value pairs when the template uses placeholders.', 'optional' => true],
                    ],
                    'returns' => 'Message send status in JSON.',
                    'example' => "curl -X POST -H \"accept:application/json\" -H \"Content-Type: application/x-www-form-urlencoded\" \\\n  -d \"api_token={api_token}\" \\\n  -d \"template_uid=TEMPLATE_UID\" \\\n  -d \"to=919999999999\" \\\n  -d \"from=919888888888\" \\\n  \"{base_url}/directmessage\"",
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/getstatusofmessage',
                    'description' => 'Get delivery status for a sent message.',
                    'parameters' => [
                        ['name' => 'message_id', 'description' => 'Message ID returned from send API.'],
                    ],
                    'returns' => 'Message status in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/getstatusofmessage" -d "api_token={api_token}" -d "message_id=MESSAGE_ID"',
                ],
            ],
        ],

        [
            'title' => 'Lists',
            'functions' => [
                [
                    'method' => 'POST',
                    'uri' => '/api/v1/lists',
                    'description' => 'Create a new audience list.',
                    'parameters' => [
                        ['name' => 'name', 'description' => 'List name.'],
                    ],
                    'returns' => 'Creation response in JSON.',
                    'example' => "curl -X POST -H \"accept:application/json\" -H \"Content-Type: application/x-www-form-urlencoded\" \\\n  -d \"api_token={api_token}\" \\\n  -d \"name=My List\" \\\n  \"{base_url}/lists\"",
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/lists',
                    'description' => 'Get all lists.',
                    'parameters' => [],
                    'returns' => 'List of lists in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/lists" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/lists/{uid}',
                    'description' => 'Get a specific list.',
                    'parameters' => [],
                    'returns' => 'List details in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/lists/{uid}" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'DELETE',
                    'uri' => '/api/v1/lists/{uid}',
                    'description' => 'Delete a list.',
                    'parameters' => [
                        ['name' => 'uid', 'description' => 'List UID.'],
                    ],
                    'returns' => 'Result message in JSON.',
                    'example' => 'curl -X DELETE -H "accept:application/json" -G "{base_url}/lists/{uid}" -d "api_token={api_token}"',
                ],
            ],
        ],

        [
            'title' => 'Subscribers',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/subscribers',
                    'description' => 'List subscribers for a list.',
                    'parameters' => [
                        ['name' => 'list_uid', 'description' => 'List UID.'],
                        ['name' => 'per_page', 'description' => 'Results per page.', 'optional' => true, 'default' => '25'],
                        ['name' => 'page', 'description' => 'Page number.', 'optional' => true, 'default' => '1'],
                    ],
                    'returns' => 'Paginated subscribers in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/subscribers" -d "api_token={api_token}" -d "list_uid=LIST_UID" -d "per_page=25" -d "page=1"',
                ],
                [
                    'method' => 'POST',
                    'uri' => '/api/v1/subscribers',
                    'description' => 'Create a subscriber in a list.',
                    'parameters' => [
                        ['name' => 'list_uid', 'description' => 'List UID.'],
                        ['name' => 'country_code', 'description' => 'Country code, e.g. +91.'],
                        ['name' => 'whatsapp_number', 'description' => 'WhatsApp number.'],
                        ['name' => 'first_name', 'description' => 'First name.'],
                        ['name' => 'last_name', 'description' => 'Last name.'],
                    ],
                    'returns' => 'Creation response in JSON.',
                    'example' => 'curl -X POST -H "accept:application/json" -G "{base_url}/subscribers" -d "api_token={api_token}" -d "list_uid=LIST_UID" -d "country_code=+91" -d "whatsapp_number=9999999999" -d "first_name=Demo" -d "last_name=User"',
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/subscribers/{uid}',
                    'description' => 'Get subscriber details.',
                    'parameters' => [
                        ['name' => 'uid', 'description' => 'Subscriber UID.'],
                    ],
                    'returns' => 'Subscriber details in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/subscribers/{uid}" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'PATCH',
                    'uri' => '/api/v1/subscribers/{uid}',
                    'description' => 'Update a subscriber.',
                    'parameters' => [
                        ['name' => 'list_uid', 'description' => 'List UID.'],
                        ['name' => 'uid', 'description' => 'Subscriber UID.'],
                        ['name' => 'country_code', 'description' => 'Country code.'],
                        ['name' => 'whatsapp_number', 'description' => 'WhatsApp number.'],
                        ['name' => 'first_name', 'description' => 'First name.'],
                        ['name' => 'last_name', 'description' => 'Last name.'],
                    ],
                    'returns' => 'Update response in JSON.',
                    'example' => 'curl -X PATCH -H "accept:application/json" -G "{base_url}/subscribers/{uid}" -d "api_token={api_token}" -d "list_uid=LIST_UID"',
                ],
                [
                    'method' => 'DELETE',
                    'uri' => '/api/v1/subscribers/{uid}',
                    'description' => 'Delete a subscriber.',
                    'parameters' => [
                        ['name' => 'uid', 'description' => 'Subscriber UID.'],
                    ],
                    'returns' => 'Result message in JSON.',
                    'example' => 'curl -X DELETE -H "accept:application/json" -G "{base_url}/subscribers/{uid}" -d "api_token={api_token}"',
                ],
            ],
        ],

        [
            'title' => 'Subscription Details',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/subscription-details',
                    'description' => 'Get current subscription plan and due date.',
                    'parameters' => [],
                    'returns' => 'Subscription details in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/subscription-details" -d "api_token={api_token}"',
                ],
            ],
        ],

        [
            'title' => 'Variables',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/variables',
                    'description' => 'List all template variables.',
                    'parameters' => [],
                    'returns' => 'Variables in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/variables" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'POST',
                    'uri' => '/api/v1/variables',
                    'description' => 'Create a variable.',
                    'parameters' => [
                        ['name' => 'type', 'description' => 'Static or Dynamic.'],
                        ['name' => 'variable_type', 'description' => 'String, Number, or URL.'],
                        ['name' => 'name', 'description' => 'Variable name.'],
                    ],
                    'returns' => 'Creation response in JSON.',
                    'example' => 'curl -X POST -H "accept:application/json" -G "{base_url}/variables" -d "api_token={api_token}" -d "type=Dynamic" -d "variable_type=String" -d "name=test-variable"',
                ],
            ],
        ],

        [
            'title' => 'Templates',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/templates',
                    'description' => 'List approved templates.',
                    'parameters' => [],
                    'returns' => 'Templates in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/templates" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/templates/{uid}',
                    'description' => 'Get a specific template.',
                    'parameters' => [
                        ['name' => 'uid', 'description' => 'Template UID.'],
                    ],
                    'returns' => 'Template details in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/templates/{uid}" -d "api_token={api_token}"',
                ],
            ],
        ],

        [
            'title' => 'Campaigns',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/campaigns',
                    'description' => 'List all campaigns.',
                    'parameters' => [],
                    'returns' => 'Campaigns in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/campaigns" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/campaigns/{uid}',
                    'description' => 'Get campaign details.',
                    'parameters' => [],
                    'returns' => 'Campaign details in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/campaigns/{uid}" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'POST',
                    'uri' => '/api/v1/campaigns',
                    'description' => 'Create and schedule a campaign.',
                    'parameters' => [
                        ['name' => 'name', 'description' => 'Campaign name.'],
                        ['name' => 'phone_number', 'description' => 'Sender WhatsApp number with country code.'],
                        ['name' => 'template_uid', 'description' => 'Template UID.'],
                        ['name' => 'list_uid', 'description' => 'Audience list UID.'],
                        ['name' => 'variables_array', 'description' => 'Per-recipient template variable values.'],
                        ['name' => 'schedule_datetime', 'description' => 'Schedule time or null to send now.', 'optional' => true],
                    ],
                    'returns' => 'Campaign creation response in JSON.',
                    'example' => 'curl -X POST -H "accept:application/json" -G "{base_url}/campaigns" -d "api_token={api_token}" -d "name=Campaign" -d "phone_number=919999999999" -d "template_uid=TEMPLATE_UID" -d "list_uid=LIST_UID"',
                ],
            ],
        ],

        [
            'title' => 'Statistics',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/business-conversations/statistics',
                    'description' => 'Business conversation statistics.',
                    'parameters' => [],
                    'returns' => 'Statistics in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/business-conversations/statistics" -d "api_token={api_token}"',
                ],
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/service-conversations/statistics',
                    'description' => 'Service conversation statistics.',
                    'parameters' => [],
                    'returns' => 'Statistics in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/service-conversations/statistics" -d "api_token={api_token}"',
                ],
            ],
        ],

        [
            'title' => 'Wallet Balance and Transactions',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/wallet-transactions',
                    'description' => 'Wallet balance and transaction history.',
                    'parameters' => [
                        ['name' => 'per_page', 'description' => 'Results per page.', 'optional' => true, 'default' => '25'],
                        ['name' => 'page', 'description' => 'Page number.', 'optional' => true, 'default' => '1'],
                    ],
                    'returns' => 'Wallet transactions in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/wallet-transactions" -d "api_token={api_token}" -d "per_page=25" -d "page=1"',
                ],
            ],
        ],

        [
            'title' => 'Business Details',
            'functions' => [
                [
                    'method' => 'GET',
                    'uri' => '/api/v1/business-details-and-phones',
                    'description' => 'WABA business profile and connected phone numbers.',
                    'parameters' => [],
                    'returns' => 'Business details in JSON.',
                    'example' => 'curl -X GET -H "accept:application/json" -G "{base_url}/business-details-and-phones" -d "api_token={api_token}"',
                ],
            ],
        ],

    ],

];
