<?php

use App\Domains\Admin\Http\Controllers\CustomerReadinessController;
use App\Domains\AiBot\Http\Controllers\AiBotController;
use App\Domains\AiBot\Http\Controllers\AiBusinessInfoController;
use App\Domains\AiBot\Http\Controllers\AiProviderKeyController;
use App\Domains\AiBot\Http\Controllers\OpenAiKeyController;
use App\Domains\Chatbot\Http\Controllers\ChatbotBuilderSupportController;
use App\Domains\Chatbot\Http\Controllers\ChatbotFlowBuilderController;
use App\Domains\Chatbot\Http\Controllers\ChatbotFlowController;
use App\Domains\Commerce\Http\Controllers\CommerceController;
use App\Domains\Dashboard\Http\Controllers\DashboardController;
use App\Domains\Inbox\Http\Controllers\InboxController;
use App\Domains\Team\Http\Controllers\ManagerSettingsController;
use App\Domains\Team\Http\Controllers\ManagerTeamController;
use App\Domains\Team\Http\Controllers\TeamController;
use App\Domains\Team\Http\Controllers\TeamImportController;
use App\Domains\Team\Http\Controllers\TeamImpersonationController;
use App\Domains\HelpCenter\Http\Controllers\FaqController;
use App\Domains\HelpCenter\Http\Controllers\TutorialController;
use App\Domains\Templates\Http\Controllers\TemplateApiController;
use App\Domains\Templates\Http\Controllers\TemplateAiController;
use App\Domains\Templates\Http\Controllers\TemplateBuilderController;
use App\Domains\Templates\Http\Controllers\InteractiveMessageController;
use App\Domains\Templates\Http\Controllers\TemplateController;
use App\Domains\Templates\Http\Controllers\TemplateVariableController;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Models\Template;
use App\Domains\TriggerTemplate\Http\Controllers\TriggerTemplateController;
use App\Domains\FormBuilder\Http\Controllers\FormBuilderController;
use App\Domains\FormBuilder\Http\Controllers\FormApiController;
use App\Domains\FormBuilder\Http\Controllers\PublicFormController;
use App\Domains\Integration\Http\Controllers\LineLoginController;
use App\Domains\ThirdParty\Http\Controllers\ShopifyController;
use App\Domains\ThirdParty\Http\Controllers\CalendlyController;
use App\Domains\ThirdParty\Http\Controllers\GoogleCalendarController;
use App\Domains\ThirdParty\Http\Controllers\GoogleCalendarBookingController;
use App\Http\Controllers\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth('web')->check() || auth('team')->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::get('/tutorial-video/{filename}', [TutorialController::class, 'stream'])
    ->where('filename', '[\w.\-]+')
    ->name('tutorials.stream');

// Profile routes moved to routes/account.php

// Public Line Login (no auth — anyone with the link can log in as a specific number)
Route::middleware('tenancy.session')->group(function () {
    Route::get('/line-login', [LineLoginController::class, 'showForm'])->name('line.login');
    Route::post('/line-login', [LineLoginController::class, 'login'])->name('line.login.submit');

    // Public webhooks (no auth)
    Route::post('/webhook/calendly', [CalendlyController::class, 'receiveWebhook'])->name('calendly.webhook');
    Route::post('/webhook/google-calendar', [GoogleCalendarController::class, 'receiveWebhook'])->name('google-calendar.webhook');

    // Google Calendar public booking page
    Route::prefix('book/google')->name('google-calendar.booking.')->group(function () {
        Route::get('/{slug}', [GoogleCalendarBookingController::class, 'show'])->name('show');
        Route::get('/{slug}/availability', [GoogleCalendarBookingController::class, 'availability'])->name('availability');
        Route::post('/{slug}', [GoogleCalendarBookingController::class, 'book'])->name('book');
    });
});

// Public commerce payment callback (Razorpay — no auth; tenant from query)
Route::middleware('web')->group(function () {
    Route::get('/commerce/payments/callback', [CommerceController::class, 'paymentCallback'])
        ->name('commerce.payment.callback');
});

// Public customer readiness form (no auth, no tenancy — stored centrally)
Route::middleware('web')->prefix('customer-readiness')->name('customer-readiness.')->group(function () {
    Route::get('/', [CustomerReadinessController::class, 'create'])->name('create');
    Route::post('/', [CustomerReadinessController::class, 'store'])->name('store');
    Route::get('/thanks', [CustomerReadinessController::class, 'thanks'])->name('thanks');
});

// Public form routes (Form Builder — no auth — tenant resolved from path)
Route::middleware([\Stancl\Tenancy\Middleware\InitializeTenancyByPath::class])
    ->prefix('form/{tenant}')
    ->name('public.form.')
    ->group(function () {
        Route::get('/{slug}', [PublicFormController::class, 'show'])->name('show');
        Route::post('/{slug}/submit', [PublicFormController::class, 'submit'])->name('submit');
        Route::get('/{slug}/embed.js', [PublicFormController::class, 'embedJs'])->name('embed');
    });

// Audience Embedded Form (list fields) — separate from Form Builder
Route::middleware([\Stancl\Tenancy\Middleware\InitializeTenancyByPath::class])
    ->prefix('lists/{tenant}/{list}')
    ->name('public.list.')
    ->group(function () {
        Route::get('/embedded-form', [\App\Domains\Audience\Http\Controllers\PublicEmbeddedFormController::class, 'show'])->name('embedded-form');
        Route::get('/embedded-form-preview', [\App\Domains\Audience\Http\Controllers\PublicEmbeddedFormController::class, 'preview'])->name('embedded-form.preview');
        Route::post('/embedded-form-subscribe', [\App\Domains\Audience\Http\Controllers\PublicEmbeddedFormController::class, 'subscribe'])->name('embedded-form.subscribe');
        Route::post('/embedded-form-subscribe-captcha', [\App\Domains\Audience\Http\Controllers\PublicEmbeddedFormController::class, 'subscribe'])->name('embedded-form.subscribe-captcha');
    });

Route::middleware(['tenancy.session', 'auth:web,team', '2fa', 'verified', 'team.redirect-dashboard'])->group(function () {
    Route::get('/api/search', GlobalSearchController::class)->name('search');

    Route::middleware('team.owner')->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/credits', [DashboardController::class, 'credits'])->name('dashboard.credits');
            Route::get('/campaign-review', [DashboardController::class, 'campaignReview'])->name('dashboard.campaign-review');
            Route::get('/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');
            Route::get('/overview', [DashboardController::class, 'overview'])->name('dashboard.overview');
            Route::get('/campaigns', [DashboardController::class, 'campaigns'])->name('dashboard.campaigns');
            Route::get('/wallet', [DashboardController::class, 'wallet'])->name('dashboard.wallet');
        });

        Route::prefix('automation/chatbot')->name('chatbot.')->group(function () {
            Route::get('/', [ChatbotFlowController::class, 'index'])->name('index');
            Route::get('/create', [ChatbotFlowController::class, 'create'])->name('create');
            Route::post('/', [ChatbotFlowController::class, 'store'])->name('store');
            Route::post('/import', [ChatbotFlowBuilderController::class, 'import'])->name('import');

            Route::get('/modals/list-message', fn () => view('automation.modals.list-message'))->name('modals.list-message');
            Route::get('/modals/buttons-message', fn () => view('automation.modals.buttons-message'))->name('modals.buttons-message');
            Route::get('/modals/single-product', fn () => view('automation.modals.single-product'))->name('modals.single-product');
            Route::get('/modals/multi-product', fn () => view('automation.modals.multi-product'))->name('modals.multi-product');

            Route::get('/{chatbotFlow}', [ChatbotFlowController::class, 'show'])->name('show');
            Route::get('/{chatbotFlow}/edit', [ChatbotFlowController::class, 'edit'])->name('edit');
            Route::put('/{chatbotFlow}', [ChatbotFlowController::class, 'update'])->name('update');
            Route::delete('/{chatbotFlow}', [ChatbotFlowController::class, 'destroy'])->name('destroy');
            Route::patch('/{chatbotFlow}/toggle', [ChatbotFlowController::class, 'toggle'])->name('toggle');
            Route::post('/{chatbotFlow}/publish', [ChatbotFlowController::class, 'publish'])->name('publish');
            Route::post('/{chatbotFlow}/duplicate', [ChatbotFlowController::class, 'duplicate'])->name('duplicate');
            Route::get('/{chatbotFlow}/stats', [ChatbotFlowController::class, 'stats'])->name('stats');

            Route::get('/{chatbotFlow}/builder-data', [ChatbotFlowBuilderController::class, 'builderData'])->name('builder-data');
            Route::get('/{chatbotFlow}/data', [ChatbotFlowBuilderController::class, 'getData'])->name('data');
            Route::post('/{chatbotFlow}/data', [ChatbotFlowBuilderController::class, 'saveData'])->name('data.save');
            Route::post('/{chatbotFlow}/clear-cache', [ChatbotFlowBuilderController::class, 'clearCache'])->name('clear-cache');
            Route::post('/{chatbotFlow}/import', [ChatbotFlowBuilderController::class, 'importToFlow'])->name('import.flow');
            Route::get('/{chatbotFlow}/export', [ChatbotFlowBuilderController::class, 'export'])->name('export');
            Route::post('/media-upload', [ChatbotBuilderSupportController::class, 'mediaUpload'])->name('media-upload');
        });

        // Legacy chatbot builder API aliases (used by ported React modules)
        Route::post('/chatbotfileupload', [ChatbotBuilderSupportController::class, 'mediaUpload']);
        Route::post('/inbox/fileuploadinpublic', [ChatbotBuilderSupportController::class, 'publicFileUpload']);
        Route::get('/getCatalogData', [ChatbotBuilderSupportController::class, 'catalogData']);
        Route::get('/getProductData', [ChatbotBuilderSupportController::class, 'productData']);
        Route::get('/getflowData', [ChatbotBuilderSupportController::class, 'flowData']);
        Route::get('/getflowJsonCode/{flowIdentifier}', [ChatbotBuilderSupportController::class, 'flowJsonCode']);
        Route::get('/template-cards/{template}', [ChatbotBuilderSupportController::class, 'templateCards']);
        Route::post('/api/test-webhook', [ChatbotBuilderSupportController::class, 'testWebhook']);

        Route::prefix('automation')->name('automation.')->group(function () {
            Route::get('/', fn () => view('automation.index'))->name('index');
            Route::redirect('/chatbot-legacy', '/automation/chatbot')->name('chatbot');
            Route::redirect('/drip-legacy', '/automation/drip')->name('drip');
            Route::redirect('/chatbot/flow', '/automation/chatbot')->name('chatbot.flow');
            Route::redirect('/chatbot/builder', '/automation/chatbot')->name('chatbot.builder');

            Route::prefix('drip')->name('drip.')->group(function () {
                Route::get('/', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'index'])->name('index');
                Route::get('/create', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'create'])->name('create');
                Route::post('/', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'store'])->name('store');
                Route::get('/{campaign}', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'show'])->name('show');
                Route::delete('/{campaign}', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'destroy'])->name('destroy');
                Route::patch('/{campaign}/toggle', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'toggle'])->name('toggle');
                Route::post('/{campaign}/duplicate', [\App\Domains\Drip\Http\Controllers\DripCampaignController::class, 'duplicate'])->name('duplicate');

                Route::get('/{campaign}/design', [\App\Domains\Drip\Http\Controllers\DripDesignController::class, 'edit'])->name('design');
                Route::put('/{campaign}/design', [\App\Domains\Drip\Http\Controllers\DripDesignController::class, 'update'])->name('design.update');

                Route::get('/{campaign}/statistics', [\App\Domains\Drip\Http\Controllers\DripStatisticsController::class, 'overview'])->name('statistics');
                Route::get('/{campaign}/statistics/detail', [\App\Domains\Drip\Http\Controllers\DripStatisticsController::class, 'detail'])->name('statistics.detail');
                Route::get('/{campaign}/statistics/export', [\App\Domains\Drip\Http\Controllers\DripStatisticsController::class, 'export'])->name('statistics.export');

                Route::get('/{campaign}/insights', [\App\Domains\Drip\Http\Controllers\DripInsightsController::class, 'show'])->name('insights');

                Route::get('/{campaign}/audience', [\App\Domains\Drip\Http\Controllers\DripAudienceController::class, 'contacts'])->name('audience');
                Route::get('/{campaign}/audience/empty', [\App\Domains\Drip\Http\Controllers\DripAudienceController::class, 'timeline'])->name('audience-empty');
                Route::post('/{campaign}/audience/trigger', [\App\Domains\Drip\Http\Controllers\DripAudienceController::class, 'trigger'])->name('audience.trigger');

                Route::get('/{campaign}/flow/data', [\App\Domains\Drip\Http\Controllers\DripFlowBuilderController::class, 'getData'])->name('flow.data');
                Route::post('/{campaign}/flow/data', [\App\Domains\Drip\Http\Controllers\DripFlowBuilderController::class, 'saveData'])->name('flow.save');
            });

            Route::prefix('events')->name('events.')->group(function () {
                Route::get('/', [\App\Domains\AutomationEvents\Http\Controllers\AutomationEventController::class, 'index'])->name('index');
                Route::post('/', [\App\Domains\AutomationEvents\Http\Controllers\AutomationEventController::class, 'store'])->name('store');
                Route::delete('/{automationEvent}', [\App\Domains\AutomationEvents\Http\Controllers\AutomationEventController::class, 'destroy'])->name('destroy');
            });

            Route::redirect('/flows', '/whatsapp-flows')->name('flows');
        });

        require __DIR__.'/whatsapp-flows.php';

        Route::prefix('trigger-template')->name('trigger-template.')->group(function () {
            Route::get('/', [TriggerTemplateController::class, 'index'])->name('index');
            Route::post('/', [TriggerTemplateController::class, 'store'])->name('store');
            Route::delete('/{triggerVariable}', [TriggerTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('tutorials')->name('tutorials.')->group(function () {
            Route::get('/', [TutorialController::class, 'index'])->name('index');
        });

        Route::get('/faqs', [FaqController::class, 'index'])->name('faqs.index');
        Route::get('/faqs/{slug}', [FaqController::class, 'index'])->name('faqs.show');

        Route::prefix('openai-key')->name('openai-key.')->group(function () {
            Route::get('/', [OpenAiKeyController::class, 'index'])->name('index');
            Route::post('/bots', [OpenAiKeyController::class, 'storeBot'])->name('bots.store');
            Route::post('/bots/{ai_bot}/toggle-default', [OpenAiKeyController::class, 'toggleDefault'])->name('bots.toggle-default');
            Route::delete('/bots/{ai_bot}', [OpenAiKeyController::class, 'destroyBot'])->name('bots.destroy');
            Route::post('/provider-keys', [OpenAiKeyController::class, 'storeProviderKey'])->name('provider-keys.store');
            Route::post('/provider-keys/{provider_key}/validate', [OpenAiKeyController::class, 'validateProviderKey'])->name('provider-keys.validate');
            Route::delete('/provider-keys/{provider_key}', [OpenAiKeyController::class, 'destroyProviderKey'])->name('provider-keys.destroy');
            Route::post('/business-info', [OpenAiKeyController::class, 'storeBusinessInfo'])->name('business-info.store');
            Route::delete('/business-info/{ai_bot}/{business_info}', [OpenAiKeyController::class, 'destroyBusinessInfo'])->name('business-info.destroy');
            Route::post('/test-bot', [OpenAiKeyController::class, 'testBot'])->name('test-bot');
            Route::post('/settings', [OpenAiKeyController::class, 'saveSettings'])->name('settings.save');
            Route::delete('/usage', [OpenAiKeyController::class, 'clearUsage'])->name('usage.clear');
        });

        Route::prefix('ai-bots')->name('ai-bots.')->group(function () {
            Route::get('/', [AiBotController::class, 'index'])->name('index');
            Route::get('/create', [AiBotController::class, 'create'])->name('create');
            Route::post('/', [AiBotController::class, 'store'])->name('store');

            Route::prefix('provider-keys')->name('provider-keys.')->group(function () {
                Route::get('/', [AiProviderKeyController::class, 'index'])->name('index');
                Route::post('/', [AiProviderKeyController::class, 'store'])->name('store');
                Route::put('/{provider_key}', [AiProviderKeyController::class, 'update'])->name('update');
                Route::delete('/{provider_key}', [AiProviderKeyController::class, 'destroy'])->name('destroy');
                Route::post('/{provider_key}/validate', [AiProviderKeyController::class, 'validateKey'])->name('validate');
            });

            Route::get('/{ai_bot}', [AiBotController::class, 'show'])->name('show');
            Route::get('/{ai_bot}/edit', [AiBotController::class, 'edit'])->name('edit');
            Route::put('/{ai_bot}', [AiBotController::class, 'update'])->name('update');
            Route::delete('/{ai_bot}', [AiBotController::class, 'destroy'])->name('destroy');
            Route::patch('/{ai_bot}/toggle-default', [AiBotController::class, 'toggleDefault'])->name('toggle-default');
            Route::get('/{ai_bot}/usage', [AiBotController::class, 'usage'])->name('usage');

            Route::prefix('{ai_bot}/business-info')->name('business-info.')->group(function () {
                Route::get('/', [AiBusinessInfoController::class, 'index'])->name('index');
                Route::post('/', [AiBusinessInfoController::class, 'store'])->name('store');
                Route::put('/{business_info}', [AiBusinessInfoController::class, 'update'])->name('update');
                Route::delete('/{business_info}', [AiBusinessInfoController::class, 'destroy'])->name('destroy');
                Route::post('/upload', [AiBusinessInfoController::class, 'upload'])->name('upload');
            });
        });

        Route::prefix('webhooks')->name('webhooks.')->group(function () {
            Route::get('/', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'index'])->name('index');
            Route::post('/', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'store'])->name('store');
            Route::put('/{webhookSubscription}', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'update'])->name('update');
            Route::delete('/{webhookSubscription}', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'destroy'])->name('destroy');
            Route::post('/{webhookSubscription}/toggle', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'toggleStatus'])->name('toggle');
            Route::post('/{webhookSubscription}/regenerate-secret', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'regenerateSecret'])->name('regenerate-secret');
            Route::post('/{webhookSubscription}/test', [\App\Domains\Webhooks\Http\Controllers\WebhookSubscriptionController::class, 'testDelivery'])->name('test');

            Route::get('/logs', [\App\Domains\Webhooks\Http\Controllers\WebhookDeliveryController::class, 'index'])->name('logs');
            Route::get('/logs/{webhookDelivery}', [\App\Domains\Webhooks\Http\Controllers\WebhookDeliveryController::class, 'show'])->name('logs.detail');
            Route::post('/logs/{webhookDelivery}/retry', [\App\Domains\Webhooks\Http\Controllers\WebhookDeliveryController::class, 'retry'])->name('logs.retry');
            Route::delete('/logs/{webhookDelivery}', [\App\Domains\Webhooks\Http\Controllers\WebhookDeliveryController::class, 'destroy'])->name('logs.destroy');
        });

        Route::prefix('commerce')->name('commerce.')->group(function () {
            Route::get('/', [CommerceController::class, 'index'])->name('index');
            Route::get('/catalog', [CommerceController::class, 'catalogList'])->name('catalog');
            Route::get('/products', [CommerceController::class, 'settings'])->name('products');
            Route::get('/product-detail', [CommerceController::class, 'productDetail'])->name('product-detail');
            Route::get('/orders', [CommerceController::class, 'orders'])->name('orders');
            Route::get('/orders/{uuid}', [CommerceController::class, 'orderDetail'])->name('orders.detail');
            Route::patch('/orders/{uuid}/status', [CommerceController::class, 'updateOrderStatus'])->name('orders.status');
            Route::get('/settings', [CommerceController::class, 'settings'])->name('settings');
            Route::post('/settings', [CommerceController::class, 'saveConfig'])->name('settings.save');
            Route::post('/payments', [CommerceController::class, 'createPayment'])->name('payments.create');
        });

        Route::prefix('integration')->name('integration.')->group(function () {
            // Shopify
            Route::get('/', [ShopifyController::class, 'index'])->name('index');
            Route::get('/shopify/scopes', [ShopifyController::class, 'scopes'])->name('shopify.scopes');
            Route::post('/shopify/domain', [ShopifyController::class, 'storeDomain'])->name('shopify.domain.store');
            Route::get('/shopify/domain', [ShopifyController::class, 'getDomain'])->name('shopify.domain.get');
            Route::post('/shopify/scopes', [ShopifyController::class, 'saveScopes'])->name('shopify.scopes.save');
            Route::get('/shopify/scopes/data', [ShopifyController::class, 'getScopes'])->name('shopify.scopes.get');
            Route::get('/shopify/data', [ShopifyController::class, 'getSendData'])->name('shopify.data');
            Route::get('/shopify/maillists', [ShopifyController::class, 'getMailLists'])->name('shopify.maillists');

            // Calendly
            Route::get('/calendly', [CalendlyController::class, 'index'])->name('calendly');
            Route::get('/calendly/connected', [CalendlyController::class, 'index'])->name('calendly.connected');
            Route::get('/calendly/events', [CalendlyController::class, 'index'])->name('calendly.events');
            Route::post('/calendly/toggle', [CalendlyController::class, 'toggle'])->name('calendly.toggle');
            Route::put('/calendly/update', [CalendlyController::class, 'update'])->name('calendly.update');
            Route::post('/calendly/test', [CalendlyController::class, 'test'])->name('calendly.test');
            Route::post('/calendly/sync', [CalendlyController::class, 'syncEvents'])->name('calendly.sync');

            // Google Calendar / Meet
            Route::get('/google-calendar', [GoogleCalendarController::class, 'index'])->name('google-calendar');
            Route::post('/google-calendar/toggle', [GoogleCalendarController::class, 'toggle'])->name('google-calendar.toggle');
            Route::put('/google-calendar/update', [GoogleCalendarController::class, 'update'])->name('google-calendar.update');
            Route::get('/google-calendar/oauth', [GoogleCalendarController::class, 'oauthRedirect'])->name('google-calendar.oauth');
            Route::get('/google-calendar/oauth/callback', [GoogleCalendarController::class, 'oauthCallback'])->name('google-calendar.oauth.callback');
            Route::post('/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google-calendar.disconnect');
            Route::post('/google-calendar/test', [GoogleCalendarController::class, 'test'])->name('google-calendar.test');
            Route::post('/google-calendar/sync', [GoogleCalendarController::class, 'syncEvents'])->name('google-calendar.sync');
            Route::post('/google-calendar/create-meeting', [GoogleCalendarController::class, 'createMeeting'])->name('google-calendar.create-meeting');
            Route::put('/google-calendar/booking-availability', [GoogleCalendarController::class, 'updateBookingAvailability'])->name('google-calendar.booking-availability');
            Route::post('/google-calendar/booking-links', [GoogleCalendarController::class, 'storeBookingLink'])->name('google-calendar.booking.store');
            Route::delete('/google-calendar/booking-links/{id}', [GoogleCalendarController::class, 'deleteBookingLink'])->name('google-calendar.booking.delete');

            // WooCommerce
            Route::get('/woocommerce', [\App\Domains\ThirdParty\Http\Controllers\WooCommerceController::class, 'index'])->name('woocommerce');
            Route::post('/woocommerce', [\App\Domains\ThirdParty\Http\Controllers\WooCommerceController::class, 'store'])->name('woocommerce.store');
            Route::delete('/woocommerce/{wooCommerceStore}', [\App\Domains\ThirdParty\Http\Controllers\WooCommerceController::class, 'destroy'])->name('woocommerce.destroy');

            // Website tracking
            Route::get('/websites', [\App\Domains\ThirdParty\Http\Controllers\WebsiteTrackingController::class, 'index'])->name('websites');
            Route::post('/websites', [\App\Domains\ThirdParty\Http\Controllers\WebsiteTrackingController::class, 'store'])->name('websites.store');
        });

        Route::prefix('form-builder')->name('form-builder.')->group(function () {
            Route::get('/', [FormBuilderController::class, 'index'])->name('index');
            Route::get('/create', [FormBuilderController::class, 'create'])->name('create');
            Route::post('/', [FormBuilderController::class, 'store'])->name('store');
            Route::get('/{form}/edit', [FormBuilderController::class, 'edit'])->name('edit');
            Route::get('/{form}/statistics', [FormBuilderController::class, 'statistics'])->name('statistics');
            Route::put('/{form}', [FormBuilderController::class, 'update'])->name('update');
            Route::delete('/{form}', [FormBuilderController::class, 'destroy'])->name('destroy');
            Route::post('/{form}/toggle', [FormBuilderController::class, 'toggleStatus'])->name('toggle');
            Route::post('/upload-logo', [FormBuilderController::class, 'uploadLogo'])->name('upload-logo');

            // API endpoints (AJAX)
            Route::prefix('api')->name('api.')->group(function () {
                Route::get('/list', [FormApiController::class, 'index'])->name('list');
                Route::post('/bulk-destroy', [FormApiController::class, 'bulkDestroy'])->name('bulk-destroy');
                Route::get('/{form}/stats', [FormApiController::class, 'stats'])->name('stats');
            });
        });
    });

    Route::middleware('team.permission:inbox_read')->prefix('inbox')->name('inbox.')->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::redirect('/compose', '/inbox')->name('compose');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/threads', [InboxController::class, 'threads'])->name('threads');
            Route::get('/templates', [InboxController::class, 'templates'])->name('templates');
            Route::post('/contacts', [InboxController::class, 'storeContact'])->name('contacts.store');
            Route::get('/export', [InboxController::class, 'exportAll'])->name('export-all');
            Route::post('/response-type/all', [InboxController::class, 'toggleAllResponseType'])->name('response-type-all');
            Route::get('/conversations/{conversation}/export', [InboxController::class, 'exportConversation'])->name('export');
            Route::post('/conversations/{conversation}/response-type', [InboxController::class, 'toggleResponseType'])->name('response-type');
            Route::get('/conversations/{conversation}/messages', [InboxController::class, 'messages'])->name('messages');
            Route::get('/conversations/{conversation}/window', [InboxController::class, 'windowStatus'])->name('window');
            Route::post('/conversations/{conversation}/messages', [InboxController::class, 'sendMessage'])->name('send');
            Route::post('/conversations/{conversation}/media', [InboxController::class, 'sendMedia'])->name('send-media');
            Route::post('/conversations/{conversation}/templates', [InboxController::class, 'sendTemplate'])->name('send-template');
            Route::post('/conversations/{conversation}/location', [InboxController::class, 'sendLocation'])->name('send-location');
            Route::post('/conversations/{conversation}/sticker', [InboxController::class, 'sendSticker'])->name('send-sticker');
            Route::post('/conversations/{conversation}/payment', [InboxController::class, 'requestPayment'])->name('request-payment');
            Route::post('/conversations/{conversation}/read', [InboxController::class, 'markRead'])->name('read');
            Route::post('/conversations/{conversation}/assign', [InboxController::class, 'assign'])->name('assign');
            Route::post('/mark-all-read', [InboxController::class, 'markAllRead'])->name('mark-all-read');
        });

        Route::get('/modals/ask-for-address', fn () => redirect()->route('inbox.index', ['modal' => 'ask-for-address']))
            ->name('modals.ask-for-address');
        Route::get('/modals/send-contact', fn () => redirect()->route('inbox.index', ['modal' => 'send-contact']))
            ->name('modals.send-contact');
        Route::get('/empty', fn () => view('inbox.empty'))->name('empty');
        Route::get('/{conversation}', [InboxController::class, 'show'])->name('show');
    });

    Route::middleware('team.permission:template_read')->prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::get('/preview', [TemplateController::class, 'index'])->name('preview');
        Route::delete('/{template}', [TemplateController::class, 'destroy'])->middleware('team.permission:template_write')->name('destroy');
        Route::post('/bulk-destroy', [TemplateController::class, 'bulkDestroy'])->middleware('team.permission:template_write')->name('bulk-destroy');
        Route::post('/ai/suggest', [TemplateAiController::class, 'suggestBodies'])->middleware('team.permission:template_write')->name('ai.suggest');
        Route::get('/variables/chatbot', [TemplateVariableController::class, 'chatbot'])->name('variables.chatbot');
        Route::get('/variables', [TemplateVariableController::class, 'index'])->name('variables');
        Route::get('/variables/create', [TemplateVariableController::class, 'create'])->name('variables.create');
        Route::post('/variables', [TemplateVariableController::class, 'store'])->name('variables.store');
        Route::get('/variables/{variable}/edit', [TemplateVariableController::class, 'edit'])->name('variables.edit');
        Route::put('/variables/{variable}', [TemplateVariableController::class, 'update'])->name('variables.update');
        Route::delete('/variables/{variable}', [TemplateVariableController::class, 'destroy'])->name('variables.destroy');
        Route::get('/variables/samples', fn () => view('templates.variables-samples'))->name('variables.samples');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/list', [TemplateApiController::class, 'index'])->name('list');
            Route::post('/refresh', [TemplateApiController::class, 'refresh'])->name('refresh');
            Route::get('/preview/{code}', [TemplateApiController::class, 'preview'])->name('preview');
            Route::get('/variables', [TemplateApiController::class, 'variables'])->name('variables');
        });

        Route::get('/free/create', [InteractiveMessageController::class, 'create'])->name('free.create');
        Route::post('/free', [InteractiveMessageController::class, 'store'])->name('free.store');
        Route::get('/free/{interactiveMessage}/edit', [InteractiveMessageController::class, 'edit'])->name('free.edit');
        Route::put('/free/{interactiveMessage}', [InteractiveMessageController::class, 'update'])->name('free.update');
        Route::delete('/free/{interactiveMessage}', [InteractiveMessageController::class, 'destroy'])->name('free.destroy');
        Route::get('/free/{interactiveMessage}/preview', [InteractiveMessageController::class, 'preview'])->name('free.preview');

        Route::get('/builder', [TemplateBuilderController::class, 'create'])->name('builder.create');
        Route::prefix('builder/{template}')->group(function () {
            Route::get('/header', [TemplateBuilderController::class, 'header'])->name('builder.header');
            Route::post('/header', [TemplateBuilderController::class, 'saveHeader'])->name('builder.header.save');
            Route::post('/header/media', [TemplateBuilderController::class, 'uploadHeaderMedia'])->name('builder.header.media');
            Route::get('/body', [TemplateBuilderController::class, 'body'])->name('builder.body');
            Route::post('/body', [TemplateBuilderController::class, 'saveBody'])->name('builder.body.save');
            Route::get('/body-media', fn (Template $template) => redirect()->route('templates.builder.body', ['template' => $template, 'modal' => 'add-variable']))->name('builder.body-media');
            Route::get('/footer', [TemplateBuilderController::class, 'footer'])->name('builder.footer');
            Route::post('/footer', [TemplateBuilderController::class, 'saveFooter'])->name('builder.footer.save');
            Route::get('/buttons', [TemplateBuilderController::class, 'buttons'])->name('builder.buttons');
            Route::post('/buttons', [TemplateBuilderController::class, 'saveButtons'])->name('builder.buttons.save');
            Route::get('/auth', [TemplateBuilderController::class, 'auth'])->name('builder.auth');
            Route::post('/auth', [TemplateBuilderController::class, 'saveAuth'])->name('builder.auth.save');
            Route::get('/lto', [TemplateBuilderController::class, 'lto'])->name('builder.lto');
            Route::post('/lto', [TemplateBuilderController::class, 'saveLto'])->name('builder.lto.save');
            Route::get('/carousel', [TemplateBuilderController::class, 'carousel'])->name('builder.carousel');
            Route::post('/carousel', [TemplateBuilderController::class, 'saveCarousel'])->name('builder.carousel.save');
            Route::get('/submit', [TemplateBuilderController::class, 'submit'])->name('builder.submit');
            Route::post('/submit', [TemplateBuilderController::class, 'saveSubmit'])->name('builder.submit.save');
        });
    });

    require __DIR__.'/features.php';

    Route::middleware('team.owner')->prefix('my-team')->name('my-team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::get('/create', [TeamController::class, 'create'])->name('create');
        Route::post('/', [TeamController::class, 'store'])->name('store');
        Route::get('/{teamMember}/edit', [TeamController::class, 'edit'])->name('edit');
        Route::put('/{teamMember}', [TeamController::class, 'update'])->name('update');
        Route::delete('/{teamMember}', [TeamController::class, 'destroy'])->name('destroy');
        Route::post('/{teamMember}/status', [TeamController::class, 'toggleStatus'])->name('status');
        Route::get('/{teamMember}/roles', [TeamController::class, 'roles'])->name('roles');
        Route::put('/{teamMember}/roles', [TeamController::class, 'updateRoles'])->name('roles.update');
    });

    Route::middleware('team.manager')->prefix('manager')->name('manager.')->group(function () {
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/', [ManagerTeamController::class, 'index'])->name('index');
            Route::get('/create', [ManagerTeamController::class, 'create'])->name('create');
            Route::post('/', [ManagerTeamController::class, 'store'])->name('store');
            Route::get('/import', [TeamImportController::class, 'form'])->name('import');
            Route::post('/import', [TeamImportController::class, 'store'])->name('import.store');
            Route::get('/import/sample', [TeamImportController::class, 'sample'])->name('import.sample');
            Route::get('/{teamMember}/edit', [ManagerTeamController::class, 'edit'])->name('edit');
            Route::put('/{teamMember}', [ManagerTeamController::class, 'update'])->name('update');
            Route::delete('/{teamMember}', [ManagerTeamController::class, 'destroy'])->name('destroy');
            Route::post('/{teamMember}/status', [ManagerTeamController::class, 'toggleStatus'])->name('status');
            Route::get('/{teamMember}/roles', [ManagerTeamController::class, 'roles'])->name('roles');
            Route::put('/{teamMember}/roles', [ManagerTeamController::class, 'updateRoles'])->name('roles.update');
            Route::post('/{teamMember}/login-as', [ManagerTeamController::class, 'loginAs'])->name('login-as');
        });

        Route::get('/settings', [ManagerSettingsController::class, 'edit'])->name('settings');
        Route::patch('/settings/auto', [ManagerSettingsController::class, 'update'])->name('settings.update');
        Route::post('/back-to-me', [TeamImpersonationController::class, 'stop'])->name('back-to-me');
    });
});
