<?php

declare(strict_types=1);

return [
    'intro' => 'A trigger is the action that starts an automation. For example, the system can trigger an automated template when someone subscribes to your audience or purchases a certain product. The system provides a wide selection of preset automation types with built-in triggers, ranging from abandoned cart templates to a simple welcome message.',

    /*
    |--------------------------------------------------------------------------
    | Legacy key aliases (stored in older campaigns)
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'subscriber_optin' => 'welcome-new-subscriber',
        'api' => 'api-3-0',
        'date_based' => 'specific-date',
        'manual' => 'api-3-0',
        'tag_added' => 'tag-added',
    ],

    'delay_before_options' => [
        '0 day' => 'On the day',
        '1 day' => '1 day before',
        '2 days' => '2 days before',
        '3 days' => '3 days before',
        '4 days' => '4 days before',
        '5 days' => '5 days before',
        '6 days' => '6 days before',
        '1 week' => '1 week before',
        '2 weeks' => '2 weeks before',
        '1 month' => '1 month before',
        '2 months' => '2 months before',
    ],

    'days_of_week' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    'groups' => [
        'Audience' => [
            'welcome-new-subscriber',
            'say-goodbye-subscriber',
            'say-happy-birthday',
            'specific-date-time-of-user',
            'subscriber-added-date',
            'tag-added',
        ],
        'Scheduled' => [
            'specific-date',
            'weekly-recurring',
            'monthly-recurring',
        ],
        'Integrations' => [
            'woo-abandoned-cart',
        ],
        'API' => [
            'api-3-0',
        ],
    ],

    'types' => [
        'welcome-new-subscriber' => [
            'label' => 'Welcome new subscribers',
            'tree' => 'New contact subscribes to list',
            'description' => 'Introduce yourself or your organization when people sign up for your audience.',
            'intro' => 'Trigger when a user subscribes to your list. It is recommended to send a welcome template to greet your new subscriber and offer your products or services.',
            'fields' => [],
        ],
        'say-goodbye-subscriber' => [
            'label' => 'Say goodbye to subscriber',
            'tree' => 'Contact unsubscribes from mail list',
            'description' => 'Send a template when a subscriber unsubscribes from your audience.',
            'intro' => 'Start the automation when a subscriber unsubscribes from your audience. You may want to ask for feedback or offer re-subscription benefits.',
            'fields' => [],
        ],
        'say-happy-birthday' => [
            'label' => 'Say Happy birthday',
            'tree' => 'Trigger on contacts\' date of birth',
            'description' => 'Celebrate with an exclusive offer or message based on the birthday field in your audience.',
            'intro' => 'Celebrate with an exclusive offer or cheerful message that sends based on the birthday field in your audience.',
            'fields' => ['before', 'at', 'field'],
        ],
        'specific-date-time-of-user' => [
            'label' => 'Specific date and time for individual user',
            'tree' => 'Trigger on contacts\' date or time',
            'description' => 'Trigger at a specific date and time for each individual user in the list.',
            'intro' => 'Trigger at a specific date and time for each individual user in the list.',
            'fields' => ['before', 'at', 'field'],
        ],
        'subscriber-added-date' => [
            'label' => 'Subscriber added date',
            'tree' => 'Trigger on contacts\' subscribe date',
            'description' => 'Send a template based on when a subscriber joined your audience.',
            'intro' => 'Automation starts yearly on your subscriber\'s joining date. You can schedule it to trigger before the date if needed.',
            'fields' => ['delay', 'at'],
        ],
        'tag-added' => [
            'label' => 'When tag is added',
            'tree' => 'Tag is added to contact',
            'description' => 'Start the automation when a specific tag is applied to a contact.',
            'intro' => 'Trigger when a tag is added to a subscriber. Select the audience above and configure the tag name below.',
            'fields' => ['tag_name'],
        ],
        'specific-date' => [
            'label' => 'Specific date',
            'tree' => 'Autostart on a scheduled date/time',
            'description' => 'Send a one-time message based on a scheduled date and time.',
            'intro' => 'Start a marketing campaign immediately or schedule it for a particular date and time. Automation will be triggered for all contacts in the selected audience list.',
            'fields' => ['date', 'at'],
        ],
        'weekly-recurring' => [
            'label' => 'Weekly recurring',
            'tree' => 'Weekly recurring',
            'description' => 'Schedule your campaign to automatically send weekly on selected days.',
            'intro' => 'Schedule your campaign to automatically send on a weekly basis, on particular week days you choose.',
            'fields' => ['days_of_week', 'at'],
        ],
        'monthly-recurring' => [
            'label' => 'Monthly recurring',
            'tree' => 'Monthly recurring',
            'description' => 'Schedule your campaign to automatically send monthly on selected days.',
            'intro' => 'Schedule your campaign to automatically send on a monthly basis, on particular days of the month.',
            'fields' => ['days_of_month', 'at'],
        ],
        'woo-abandoned-cart' => [
            'label' => 'Abandoned cart reminder',
            'tree' => 'Abandoned Cart Reminder',
            'description' => 'Send follow-up messages when someone abandons their cart without purchasing.',
            'intro' => 'Send follow-up messages to someone who added items to their cart and left without completing checkout.',
            'fields' => ['woo_source'],
        ],
        'api-3-0' => [
            'label' => 'API 3.0',
            'tree' => 'Trigger manually via API 3.0 request',
            'description' => 'Trigger an automation series with an API call from your application.',
            'intro' => 'Automation is triggered manually or from another application using API 3.0. Use the endpoint shown below after saving your campaign.',
            'fields' => ['api_endpoint'],
        ],
    ],
];
