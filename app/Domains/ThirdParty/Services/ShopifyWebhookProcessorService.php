<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\Campaigns\Services\CampaignTestMessageService;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Models\ShopifyIntegration;
use App\Domains\ThirdParty\Models\ShopifySendData;
use App\Models\Contact;
use App\Models\ShopifyWebhookEvent;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShopifyWebhookProcessorService
{
    public function __construct(
        private readonly CampaignTestMessageService $testMessageService,
    ) {}

    public function processPending(int $limit = 50): int
    {
        $events = ShopifyWebhookEvent::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        foreach ($events as $event) {
            try {
                $this->processEvent($event);
                $processed++;
            } catch (Throwable $e) {
                report($e);
                $event->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
            }
        }

        return $processed;
    }

    public function processEvent(ShopifyWebhookEvent $event): void
    {
        $tenant = Tenant::query()->find($event->tenant_id);
        if ($tenant === null) {
            $event->update([
                'status' => 'failed',
                'error_message' => 'Tenant not found.',
                'processed_at' => now(),
            ]);

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $integration = ShopifyIntegration::query()
                ->where('status', IntegrationStatus::Enabled)
                ->get()
                ->first(function (ShopifyIntegration $row) use ($event): bool {
                    $domain = strtolower((string) ($row->domainUrl() ?? ''));
                    $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
                    $domain = rtrim($domain, '/');

                    return $domain !== '' && $domain === strtolower((string) $event->shop_domain);
                });

            if ($integration === null) {
                $event->update([
                    'status' => 'skipped',
                    'error_message' => 'No enabled Shopify integration for shop domain.',
                    'processed_at' => now(),
                ]);

                return;
            }

            $settings = is_array($integration->settings) ? $integration->settings : [];
            $scopes = $settings['access_scope_check'] ?? [];
            if (! $this->scopeEnabled($scopes, (string) $event->topic)) {
                $event->update([
                    'status' => 'skipped',
                    'error_message' => 'Scope disabled for topic '.$event->topic,
                    'processed_at' => now(),
                ]);

                return;
            }

            $templateId = $this->templateIdForTopic($settings, (string) $event->topic);
            if ($templateId === null) {
                $event->update([
                    'status' => 'skipped',
                    'error_message' => 'No template mapped for topic '.$event->topic,
                    'processed_at' => now(),
                ]);

                return;
            }

            $template = Template::query()->find($templateId);
            if ($template === null) {
                $event->update([
                    'status' => 'failed',
                    'error_message' => 'Template #'.$templateId.' not found.',
                    'processed_at' => now(),
                ]);

                return;
            }

            if ($template->status !== TemplateStatus::Approved) {
                $event->update([
                    'status' => 'skipped',
                    'error_message' => 'Template #'.$templateId.' is not approved.',
                    'processed_at' => now(),
                ]);

                return;
            }

            $line = WhatsappLine::query()->where('is_default', true)->first()
                ?? WhatsappLine::query()->first();
            if ($line === null) {
                $event->update([
                    'status' => 'failed',
                    'error_message' => 'No WhatsApp Phone Number configured.',
                    'processed_at' => now(),
                ]);

                return;
            }

            $phones = $this->resolvePhones($event, $settings);
            if ($phones === []) {
                ShopifySendData::query()->create([
                    'user_id' => $integration->user_id,
                    'event_type' => $event->topic,
                    'payload' => [
                        'shopify_webhook_event_id' => $event->id,
                        'shop_domain' => $event->shop_domain,
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                        'reason' => 'No WhatsApp number found on webhook payload / mail list.',
                    ],
                    'whatsapp_number' => null,
                    'status' => MessageLogStatus::Skipped->value,
                    'sent_at' => now(),
                ]);

                $event->update([
                    'status' => 'skipped',
                    'error_message' => 'No recipients found for topic '.$event->topic,
                    'processed_at' => now(),
                ]);

                return;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
            $templateVariables = $this->extractTemplateVariables($template, $payload);

            $sent = 0;
            foreach ($phones as $phone) {
                try {
                    $this->testMessageService->sendDirect(
                        line: $line,
                        template: $template,
                        phone: $phone,
                        templateVariables: $templateVariables,
                    );
                    $sent++;

                    ShopifySendData::query()->create([
                        'user_id' => $integration->user_id,
                        'event_type' => $event->topic,
                        'payload' => [
                            'shopify_webhook_event_id' => $event->id,
                            'shop_domain' => $event->shop_domain,
                            'template_id' => $template->id,
                            'template_name' => $template->name,
                            'source' => 'shopify_webhook',
                        ],
                        'whatsapp_number' => $phone,
                        'status' => MessageLogStatus::Sent->value,
                        'sent_at' => now(),
                    ]);
                } catch (Throwable $e) {
                    Log::warning('Shopify webhook template send failed', [
                        'event_id' => $event->id,
                        'phone' => $phone,
                        'error' => $e->getMessage(),
                    ]);

                    ShopifySendData::query()->create([
                        'user_id' => $integration->user_id,
                        'event_type' => $event->topic,
                        'payload' => [
                            'shopify_webhook_event_id' => $event->id,
                            'template_id' => $template->id,
                            'template_name' => $template->name,
                            'error' => $e->getMessage(),
                            'source' => 'shopify_webhook',
                        ],
                        'whatsapp_number' => $phone,
                        'status' => MessageLogStatus::Failed->value,
                        'sent_at' => now(),
                    ]);
                }
            }

            $event->update([
                'status' => 'processed',
                'error_message' => $sent === 0 ? 'No recipients sent.' : null,
                'processed_at' => now(),
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * @param  array<int, array{key?: string, value?: string}>|mixed  $scopes
     */
    private function scopeEnabled(mixed $scopes, string $topic): bool
    {
        if (! is_array($scopes)) {
            return false;
        }

        foreach ($scopes as $scope) {
            if (! is_array($scope)) {
                continue;
            }
            if (($scope['key'] ?? null) === $topic && strtolower((string) ($scope['value'] ?? '')) === 'yes') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function templateIdForTopic(array $settings, string $topic): ?int
    {
        $selected = $settings['template_selected'] ?? $settings['templateselected'] ?? [];
        if (! is_array($selected)) {
            return null;
        }

        foreach ($selected as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['key'] ?? null) === $topic && filled($row['value'] ?? null)) {
                return (int) $row['value'];
            }
        }

        return null;
    }

    /**
     * Resolve $(path) placeholders from the template body against the Shopify webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function extractTemplateVariables(Template $template, array $payload): array
    {
        $body = (string) data_get($template->payload, 'body.text', '');
        if ($body === '' && filled($template->body_preview)) {
            $body = (string) $template->body_preview;
        }

        preg_match_all('/\$\(([^)]+)\)/', $body, $matches);
        $keys = $matches[1] ?? [];
        $variables = [];

        foreach ($keys as $key) {
            $path = str_replace('->', '.', (string) $key);
            $value = Arr::get($payload, $path);
            $variables[$key] = is_scalar($value) && (string) $value !== ''
                ? (string) $value
                : 'default';

            if (str_contains($path, '.')) {
                $short = last(explode('.', $path));
                if (is_string($short) && $short !== '' && ! array_key_exists($short, $variables)) {
                    $variables[$short] = $variables[$key];
                }
            }
        }

        $customerName = trim(implode(' ', array_filter([
            Arr::get($payload, 'customer.first_name'),
            Arr::get($payload, 'customer.last_name'),
        ])));
        if ($customerName === '') {
            $customerName = (string) (Arr::get($payload, 'billing_address.name')
                ?? Arr::get($payload, 'shipping_address.name')
                ?? 'Customer');
        }

        $variables['full_name'] = $variables['full_name'] ?? $customerName;
        $variables['first_name'] = $variables['first_name'] ?? (string) (Arr::get($payload, 'customer.first_name') ?: 'Customer');
        $variables['last_name'] = $variables['last_name'] ?? (string) (Arr::get($payload, 'customer.last_name') ?: '');
        $variables['name'] = $variables['name'] ?? $customerName;
        $variables['order_id'] = $variables['order_id'] ?? (string) (Arr::get($payload, 'name') ?? Arr::get($payload, 'order_number') ?? Arr::get($payload, 'id') ?? '');
        $variables['total_price'] = $variables['total_price'] ?? (string) (Arr::get($payload, 'total_price') ?? '');

        return $variables;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function resolvePhones(ShopifyWebhookEvent $event, array $settings): array
    {
        $topic = (string) $event->topic;
        $productColumns = [
            'product_listings_add',
            'product_listings_remove',
            'product_listings_update',
            'products_create',
            'products_delete',
            'products_update',
        ];

        if (in_array($topic, $productColumns, true)) {
            $mailListId = $settings[$topic] ?? null;
            if (! filled($mailListId)) {
                return [];
            }

            return Contact::query()
                ->where('mail_list_id', (int) $mailListId)
                ->pluck('phone')
                ->map(fn ($phone) => PhoneNormalizer::normalize((string) $phone) ?? preg_replace('/\D+/', '', (string) $phone))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $payload = is_array($event->payload) ? $event->payload : [];
        $candidates = [
            Arr::get($payload, 'phone'),
            Arr::get($payload, 'customer.phone'),
            Arr::get($payload, 'billing_address.phone'),
            Arr::get($payload, 'shipping_address.phone'),
            Arr::get($payload, 'default_address.phone'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate) || trim((string) $candidate) === '') {
                continue;
            }
            $normalized = PhoneNormalizer::normalize((string) $candidate)
                ?? preg_replace('/\D+/', '', (string) $candidate);
            if (is_string($normalized) && $normalized !== '') {
                return [$normalized];
            }
        }

        return [];
    }
}
