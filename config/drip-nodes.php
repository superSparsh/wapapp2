<?php

declare(strict_types=1);

return [
    'intro' => 'Streamline your communication and personalize each customer\'s experience. Use automation to create a template or series of templates that send when triggered by a specific date, event, or contact\'s activities.',

    'legacy_actions' => [
        'templateMessage' => [
            'label' => 'Send a template',
            'description' => 'Send an automated WhatsApp template to contacts who reach this step.',
            'icon' => 'document-text.svg',
            'coming_soon' => false,
        ],
        'delay' => [
            'label' => 'Wait',
            'description' => 'Add a wait time before proceeding with the next action.',
            'icon' => 'clock.svg',
            'coming_soon' => false,
        ],
        'condition' => [
            'label' => 'Evaluate a condition',
            'description' => 'Take actions when a condition is met. For example: a previous WhatsApp message is read, delivered, or replied to.',
            'icon' => 'hierarchy-3.svg',
            'coming_soon' => false,
        ],
        'contactOperation' => [
            'label' => 'Operation',
            'description' => 'Perform an operation like update, copy, move, or tag contacts.',
            'icon' => 'scroll.svg',
            'coming_soon' => false,
        ],
    ],

    'categories' => [
        'Messages' => [
            'icon' => 'message-text.svg',
            'default' => true,
        ],
        'Logic & Control' => [
            'icon' => 'hierarchy-3.svg',
            'default' => false,
        ],
        'Integration' => [
            'icon' => 'scroll.svg',
            'default' => false,
        ],
        'Timing' => [
            'icon' => 'clock.svg',
            'default' => false,
        ],
    ],

    'types' => [
        'welcomeMessage' => [
            'label' => 'Welcome Message',
            'description' => 'Send an automated welcome message when a contact enters the flow.',
            'category' => 'Messages',
            'icon' => 'message-notif.svg',
            'coming_soon' => false,
        ],
        'templateMessage' => [
            'label' => 'Template Message',
            'description' => 'Send an automated WhatsApp template to contacts who reach this step.',
            'category' => 'Messages',
            'icon' => 'document-text.svg',
            'coming_soon' => false,
        ],
        'interactiveMessage' => [
            'label' => 'Interactive Message',
            'description' => 'Send buttons, lists, or quick replies to collect a response.',
            'category' => 'Messages',
            'icon' => 'messages.svg',
            'coming_soon' => false,
        ],
        'carouselTemplate' => [
            'label' => 'Carousel Template',
            'description' => 'Send a multi-card carousel template in one step.',
            'category' => 'Messages',
            'icon' => 'car.svg',
            'coming_soon' => false,
        ],
        'whatsappFlowTemplate' => [
            'label' => 'WhatsApp Flow Template',
            'description' => 'Launch a WhatsApp Flow to collect structured responses.',
            'category' => 'Messages',
            'icon' => 'routing-2.svg',
            'coming_soon' => false,
        ],
        'mediaMessage' => [
            'label' => 'Media Message',
            'description' => 'Send an image, video, audio, or document to the contact.',
            'category' => 'Messages',
            'icon' => 'gallery.svg',
            'coming_soon' => false,
        ],
        'condition' => [
            'label' => 'Condition',
            'description' => 'Branch the flow when a previous message is read, delivered, or replied to.',
            'category' => 'Logic & Control',
            'icon' => 'hierarchy-3.svg',
            'coming_soon' => false,
        ],
        'enhancedCondition' => [
            'label' => 'Enhanced Condition',
            'description' => 'Evaluate advanced rules with multiple criteria before continuing.',
            'category' => 'Logic & Control',
            'icon' => 'hierarchy-3.svg',
            'coming_soon' => false,
        ],
        'waitForResponse' => [
            'label' => 'Wait for Response',
            'description' => 'Pause the automation until the contact replies to a message.',
            'category' => 'Logic & Control',
            'icon' => 'message-notif.svg',
            'coming_soon' => false,
        ],
        'jumpToStep' => [
            'label' => 'Jump to Step',
            'description' => 'Move the contact to another step in the same automation.',
            'category' => 'Logic & Control',
            'icon' => 'routing-2.svg',
            'coming_soon' => false,
        ],
        'httpRequest' => [
            'label' => 'HTTP Request',
            'description' => 'Call an external API and use the response in later steps.',
            'category' => 'Integration',
            'icon' => 'scroll.svg',
            'coming_soon' => false,
        ],
        'functionCall' => [
            'label' => 'Function Call',
            'description' => 'Run a server-side function as part of the automation.',
            'category' => 'Integration',
            'icon' => 'scroll.svg',
            'coming_soon' => false,
        ],
        'delay' => [
            'label' => 'Delay',
            'description' => 'Wait for a period of time before sending the next message.',
            'category' => 'Timing',
            'icon' => 'clock.svg',
            'coming_soon' => false,
        ],
        'typingIndicator' => [
            'label' => 'Typing Indicator',
            'description' => 'Show a typing indicator before the next message is sent.',
            'category' => 'Timing',
            'icon' => 'more.svg',
            'coming_soon' => false,
        ],
        'contactOperation' => [
            'label' => 'Operation',
            'description' => 'Perform an operation like update, copy, move, or tag contacts.',
            'category' => 'Integration',
            'icon' => 'scroll.svg',
            'coming_soon' => false,
        ],
    ],
];
