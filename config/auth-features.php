<?php

declare(strict_types=1);

return [
    'carousel_interval_ms' => (int) env('AUTH_FEATURES_CAROUSEL_INTERVAL', 6000),

    'slides' => [
        [
            'label' => 'Unified WhatsApp Inbox',
            'title' => 'Chat Smarter, All in One Place',
            'description' => 'Manage every customer conversation from one powerful inbox with tags, assignments, and quick replies.',
            'image' => 'images/auth/features/feature-chat-with-customers.png',
        ],
        [
            'label' => 'Smart Chatbots',
            'title' => 'Automate Replies with Smart Chatbots',
            'description' => 'Build no-code flows that answer FAQs, qualify leads, and route chats to the right team member.',
            'image' => 'images/auth/features/feature-smart-chatbots.png',
        ],
        [
            'label' => 'Campaign Analytics',
            'title' => 'Track Campaigns in Real-Time',
            'description' => 'See delivery, read rates, and replies instantly so you can optimize every broadcast.',
            'image' => 'images/auth/features/feature-easy-reports.png',
        ],
        [
            'label' => 'Team Collaboration',
            'title' => 'Collaborate with Your Team Seamlessly',
            'description' => 'Assign chats, share notes, and work together on one WhatsApp number without confusion.',
            'image' => 'images/auth/features/feature-team-inbox.png',
        ],
        [
            'label' => 'App Integrations',
            'title' => 'Connect Shopify, Zoho & More',
            'description' => 'Sync orders, contacts, and CRM data to trigger WhatsApp messages automatically.',
            'image' => 'images/auth/features/feature-app-integrations.png',
        ],
        [
            'label' => 'Live Notifications',
            'title' => 'Get Instant Alerts for Every Message',
            'description' => 'Never miss a lead — get real-time alerts when customers message or reply to campaigns.',
            'image' => 'images/auth/features/feature-instant-notifications.png',
        ],
        [
            'label' => 'Global Reach',
            'title' => 'Reach Customers Around the World',
            'description' => 'Send compliant WhatsApp messages to customers across countries from a single dashboard.',
            'image' => 'images/auth/features/feature-send-worldwide.png',
        ],
        [
            'label' => 'Message Templates',
            'title' => 'Send Engaging Templates & Media',
            'description' => 'Use approved templates with images, buttons, and variables for higher engagement.',
            'image' => 'images/auth/features/feature-message-templates.png',
        ],
        [
            'label' => 'Smart Segmentation',
            'title' => 'Deliver Personalized Messages That Convert',
            'description' => 'Group contacts by behavior and send targeted campaigns that feel personal.',
            'image' => 'images/auth/features/feature-smart-segmentation.png',
        ],
    ],
];
