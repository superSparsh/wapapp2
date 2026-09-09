<?php

declare(strict_types=1);

/**
 * Module keys for admin error logs and queue filtering.
 *
 * Resolution order for a class/source string:
 * 1. Exact class match in `job_classes`
 * 2. First matching namespace prefix in `namespaces` (longest match wins)
 * 3. CAMS Action map (`cams_actions`) for API errors
 * 4. Fallback: `other`
 */
return [
    'modules' => [
        'inbox' => 'Inbox',
        'campaigns' => 'Campaigns',
        'commerce' => 'Commerce',
        'templates' => 'Templates',
        'chatbot' => 'Chatbot',
        'drip' => 'Drip',
        'webhooks' => 'Webhooks',
        'billing' => 'Billing',
        'integration' => 'Integration',
        'auth' => 'Auth',
        'forms' => 'Forms',
        'third_party' => 'Third Party',
        'whatsapp_flow' => 'WhatsApp Flow',
        'other' => 'Other',
    ],

    /** @var array<class-string, string> */
    'job_classes' => [
        \App\Domains\Inbox\Jobs\SendOutboundMessageJob::class => 'inbox',
        \App\Domains\Campaigns\Jobs\SendCampaignRecipientJob::class => 'campaigns',
        \App\Domains\Commerce\Jobs\SendPaymentLinkJob::class => 'commerce',
        \App\Domains\Templates\Jobs\SubmitTemplateJob::class => 'templates',
        \App\Domains\Templates\Jobs\DeleteTemplateJob::class => 'templates',
        \App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob::class => 'chatbot',
        \App\Domains\Drip\Jobs\ExecuteDripStepJob::class => 'drip',
        \App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob::class => 'webhooks',
        \App\Domains\Webhooks\Jobs\DispatchOutboundWebhookJob::class => 'webhooks',
        \App\Domains\FormBuilder\Jobs\ProcessFormSubmissionJob::class => 'forms',
        \App\Domains\ThirdParty\Jobs\SyncCalendlyEventsJob::class => 'third_party',
        \App\Domains\ThirdParty\Jobs\SyncGoogleCalendarEventsJob::class => 'third_party',
        \App\Domains\ThirdParty\Jobs\RenewGoogleCalendarWatchJob::class => 'third_party',
    ],

    /**
     * Namespace prefix → module (checked longest-first).
     *
     * @var array<string, string>
     */
    'namespaces' => [
        'App\\Domains\\Inbox\\' => 'inbox',
        'App\\Domains\\Campaigns\\' => 'campaigns',
        'App\\Domains\\Commerce\\' => 'commerce',
        'App\\Domains\\Templates\\' => 'templates',
        'App\\Domains\\Chatbot\\' => 'chatbot',
        'App\\Domains\\Drip\\' => 'drip',
        'App\\Domains\\Webhooks\\' => 'webhooks',
        'App\\Domains\\Billing\\' => 'billing',
        'App\\Domains\\Integration\\' => 'integration',
        'App\\Domains\\Auth\\' => 'auth',
        'App\\Domains\\FormBuilder\\' => 'forms',
        'App\\Domains\\ThirdParty\\' => 'third_party',
        'App\\Domains\\WhatsappFlow\\' => 'whatsapp_flow',
        'App\\Domains\\WhatsApp\\' => 'inbox',
        'App\\Domains\\AiBot\\' => 'chatbot',
        'App\\Domains\\Audience\\' => 'inbox',
        'App\\Domains\\AutomationEvents\\' => 'drip',
        'App\\Domains\\TriggerTemplate\\' => 'templates',
    ],

    /**
     * CAMS Action → module for API error attribution.
     *
     * @var array<string, string>
     */
    'cams_actions' => [
        'SendChatappMessage' => 'inbox',
        'SendChatappMassMessage' => 'campaigns',
        'ListProductCatalog' => 'commerce',
        'ListProduct' => 'commerce',
        'CreateChatappTemplate' => 'templates',
        'GetChatappTemplateDetail' => 'templates',
        'DeleteChatappTemplate' => 'templates',
        'ModifyChatappTemplateReview' => 'templates',
        'ListChatappTemplate' => 'templates',
        'CreateFlow' => 'whatsapp_flow',
        'UpdateFlowJSONAsset' => 'whatsapp_flow',
        'PublishFlow' => 'whatsapp_flow',
        'ListFlow' => 'whatsapp_flow',
        'GetFlowPreviewUrl' => 'whatsapp_flow',
        'GetFlowJSONAssest' => 'whatsapp_flow',
        'DeprecateFlow' => 'whatsapp_flow',
        'DeleteFlow' => 'whatsapp_flow',
        'ChatappBindWaba' => 'integration',
        'ChatappSyncPhoneNumber' => 'integration',
        'QueryWabaBusinessInfo' => 'integration',
        'QueryPhoneBusinessProfile' => 'integration',
        'ModifyPhoneBusinessProfile' => 'integration',
        'UpdatePhoneWebhook' => 'integration',
        'AddChatappPhoneNumber' => 'integration',
    ],
];
