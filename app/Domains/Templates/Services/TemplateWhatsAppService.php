<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Domains\WhatsApp\Services\CamsTemplateMediaUploader;
use App\Domains\WhatsApp\Support\CamsComponentEncoder;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

class TemplateWhatsAppService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
        private readonly CamsTemplateMediaUploader $mediaUploader,
    ) {}

    /**
     * Submit a new template to WhatsApp for approval.
     */
    public function submitTemplate(Template $template): bool
    {
        if (! $this->camsClient->isConfigured()) {
            Log::warning('Cams client not configured; skipping template submission.', ['template_id' => $template->id]);

            return false;
        }

        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            Log::warning('No WhatsApp line configured for template submission.', ['template_id' => $template->id]);

            return false;
        }

        // If we already have a real CAMS TemplateCode, modify instead of create.
        if (filled($template->whatsappCode())) {
            return $this->modifyTemplate($template);
        }

        $name = $this->normalizeName($template->name);
        $extra = ['CustSpaceId' => $line->alibaba_cust_space_id];

        // Add example data if body has variables
        $example = $this->buildExample($template);
        if (! empty($example)) {
            // Nested map → Example.body_text.1=… (do not JSON-encode)
            $extra['Example'] = $example;
        }

        // Authentication template TTL
        $payload = $template->wizardPayload();
        $auth = $payload['auth'] ?? [];
        if ($template->category === 'AUTHENTICATION' && ($auth['message_validity'] ?? false)) {
            $extra['MessageSendTtlSeconds'] = (string) ($auth['validity_seconds'] ?? 120);
        }

        try {
            $this->ensureProviderMediaUrls($template, (string) $line->alibaba_cust_space_id);
            $components = $this->buildComponents($template);
            $response = $this->camsClient->createChatappTemplate(
                $name,
                CamsTemplateIdentity::language($template->language),
                TemplateCategoryCatalog::whatsAppCategory((string) $template->category),
                $components,
                $extra,
            );

            $body = $response->json() ?? [];
            if (! $this->isCamsSuccess($response->successful(), is_array($body) ? $body : [])) {
                $this->handleSubmissionError($template, is_string($response->body()) ? $response->body() : json_encode($body));

                return false;
            }

            $templateCode = $this->extractTemplateCode(is_array($body) ? $body : []);
            $previous = $template->status;
            $template->update([
                'code' => $templateCode ?: $template->code,
                'status' => TemplateStatus::PendingReview,
                'synced_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->logStatusChange($template, $previous, TemplateStatus::PendingReview);

            return true;
        } catch (\Throwable $e) {
            $this->handleSubmissionError($template, $e->getMessage());

            return false;
        }
    }

    /**
     * Modify (re-submit) an existing edited template.
     */
    public function modifyTemplate(Template $template): bool
    {
        $providerCode = $template->whatsappCode();
        if (! $providerCode) {
            return $this->submitTemplate($template);
        }

        if (! $this->camsClient->isConfigured()) {
            Log::warning('Cams client not configured; skipping template modify.', ['template_id' => $template->id]);

            return false;
        }

        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            Log::warning('No WhatsApp line configured for template modify.', ['template_id' => $template->id]);

            return false;
        }

        $name = $this->normalizeName($template->name);
        $extra = ['CustSpaceId' => $line->alibaba_cust_space_id];

        try {
            $this->ensureProviderMediaUrls($template, (string) $line->alibaba_cust_space_id);
            $components = $this->buildComponents($template);
            $response = $this->camsClient->modifyChatappTemplate(
                $providerCode,
                $name,
                CamsTemplateIdentity::language($template->language),
                TemplateCategoryCatalog::whatsAppCategory((string) $template->category),
                $components,
                $extra,
            );

            $body = $response->json() ?? [];
            if (! $this->isCamsSuccess($response->successful(), is_array($body) ? $body : [])) {
                $this->handleSubmissionError($template, is_string($response->body()) ? $response->body() : json_encode($body));

                return false;
            }

            $previous = $template->status;
            $template->update([
                'status' => TemplateStatus::PendingReview,
                'synced_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->logStatusChange($template, $previous, TemplateStatus::PendingReview);

            return true;
        } catch (\Throwable $e) {
            $this->handleSubmissionError($template, $e->getMessage());

            return false;
        }
    }

    /**
     * Delete a template from WhatsApp API.
     */
    public function deleteTemplate(Template $template): bool
    {
        $code = $template->whatsappCode();
        if (! $code) {
            return true; // No code = never submitted, just soft-delete locally
        }

        if (! $this->camsClient->isConfigured()) {
            return false;
        }

        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            return false;
        }

        try {
            $response = $this->camsClient->deleteChatappTemplate([
                'TemplateCode' => $code,
                'CustSpaceId' => $line->alibaba_cust_space_id,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Failed to delete template from WhatsApp', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build the WhatsApp API components array from template payload.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildComponents(Template $template): array
    {
        $payload = $template->wizardPayload();

        if ($template->category === 'AUTHENTICATION') {
            return $this->buildAuthenticationComponents($payload);
        }

        // Carousel only when explicitly enabled (legacy is_carousel_template), not for all Marketing
        $carousel = $payload['carousel'] ?? [];
        $carouselEnabled = (bool) ($carousel['enabled'] ?? false)
            || TemplateCategoryCatalog::isCarousel((string) $template->category);

        if ($carouselEnabled) {
            if (! filled($carousel['body'] ?? null)) {
                $carousel['body'] = (string) ($payload['body']['text'] ?? '');
            }

            return $this->buildCarouselComponents($carousel);
        }

        return $this->buildStandardComponents($payload, $template);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildStandardComponents(array $payload, Template $template): array
    {
        $components = [];

        // BODY
        $components[] = [
            'type' => 'BODY',
            'text' => (string) ($payload['body']['text'] ?? ''),
            'format' => 'TEXT',
        ];

        // LTO component
        $lto = $payload['lto'] ?? [];
        if ($lto['enabled'] ?? false) {
            $components[] = [
                'type' => 'LIMITED_TIME_OFFER',
                'text' => (string) ($lto['discount_introduction'] ?? ''),
                'hasExpiration' => (bool) ($lto['expiration_time'] ?? false),
                'codeExpirationMinutes' => $lto['expiration_time'] ? ($lto['time_variable'] ?? null) : null,
            ];
        }

        // HEADER
        $header = $payload['header'] ?? [];
        $headerType = (string) ($header['type'] ?? 'none');
        if ($headerType === 'text') {
            $components[] = [
                'type' => 'HEADER',
                'text' => (string) ($header['text'] ?? ''),
                'format' => 'TEXT',
            ];
        } elseif ($headerType === 'image') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'IMAGE',
                'url' => $this->headerMediaUrl($header),
            ];
        } elseif ($headerType === 'video') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'VIDEO',
                'url' => $this->headerMediaUrl($header),
            ];
        } elseif ($headerType === 'document') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'DOCUMENT',
                'url' => $this->headerMediaUrl($header),
                'fileName' => (string) ($header['doc_name'] ?? 'document'),
            ];
        } elseif ($headerType === 'location') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'LOCATION',
            ];
        }

        // FOOTER (skip for opt-out — WhatsApp handles it via isOptOut flag)
        $footerText = trim((string) ($payload['footer']['text'] ?? ''));
        $isOptOut = (bool) ($payload['is_opt_out'] ?? false);
        if ($footerText !== '' && ! $isOptOut) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footerText,
                'format' => 'TEXT',
            ];
        }

        // BUTTONS
        $buttons = $this->buildButtons($payload);
        if (! empty($buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildAuthenticationComponents(array $payload): array
    {
        $auth = $payload['auth'] ?? [];

        $buttonType = 'COPY_CODE';
        if ($auth['auto_fill'] ?? false) {
            $buttonType = ($auth['zero_tap'] ?? false) ? 'ZERO_TAP' : 'ONE_TAP';
        }

        $button = [
            'type' => $buttonType,
            'text' => $auth['auto_fill'] ? 'Copy Code' : ($auth['copy_button_text'] ?? 'Copy Code'),
        ];

        if ($auth['auto_fill'] ?? false) {
            $button['autofillText'] = $auth['fill_button_text'] ?? 'Autofill';
        }

        if ($auth['zero_tap'] ?? false) {
            $button['zeroTapTermsAccepted'] = true;
        }

        $supportedApps = $auth['supported_apps'] ?? [];
        if (! empty($supportedApps)) {
            $button['supportedApps'] = collect($supportedApps)
                ->filter(fn ($app) => ! empty($app['package_name']) && ! empty($app['signature_hash']))
                ->map(fn ($app) => [
                    'packageName' => $app['package_name'],
                    'signatureHash' => $app['signature_hash'],
                ])
                ->take(55)
                ->values()
                ->all();
        }

        $buttonsComponent = [
            'type' => 'BUTTONS',
            'buttons' => [$button],
        ];

        if ($auth['message_validity'] ?? false) {
            $buttonsComponent['duration'] = $auth['validity_seconds'] ?? 120;
        }

        if ($auth['expiration_time'] ?? false) {
            $buttonsComponent['codeExpirationMinutes'] = $auth['expiration_minutes'] ?? 120;
        }

        $components = [$buttonsComponent];

        $components[] = [
            'type' => 'BODY',
            'addSecretRecommendation' => (bool) ($auth['add_secret_recommendation'] ?? false),
        ];

        $components[] = [
            'type' => 'FOOTER',
            ...($auth['expiration_time'] ?? false ? ['codeExpirationMinutes' => $auth['expiration_minutes'] ?? 120] : []),
        ];

        return $components;
    }

    /**
     * @param  array<string, mixed>  $carousel
     * @return array<int, array<string, mixed>>
     */
    private function buildCarouselComponents(array $carousel): array
    {
        $cards = collect($carousel['cards'] ?? [])
            ->filter(fn ($card) => is_array($card) && filled($card['body'] ?? null))
            ->map(function (array $card): array {
                // Legacy / CAMS: each card has CardComponents[HEADER, BODY, BUTTONS]
                $cardComponents = [
                    [
                        'type' => 'HEADER',
                        'format' => strtoupper((string) ($card['header'] ?? $card['header_type'] ?? 'IMAGE')),
                        'url' => (string) ($card['media_url'] ?? $card['url'] ?? ''),
                    ],
                    [
                        'type' => 'BODY',
                        'text' => (string) ($card['body'] ?? ''),
                    ],
                ];

                $cardButtons = collect($card['buttons'] ?? [])
                    ->filter(fn ($btn) => is_array($btn) && filled($btn['text'] ?? null))
                    ->map(function (array $btn): array {
                        $type = strtoupper((string) ($btn['type'] ?? 'QUICK_REPLY'));

                        if ($type === 'URL') {
                            return [
                                'type' => 'URL',
                                'text' => (string) $btn['text'],
                                'url' => (string) ($btn['url'] ?? ''),
                            ];
                        }

                        if ($type === 'PHONE_NUMBER' || $type === 'PHONE') {
                            return [
                                'type' => 'PHONE_NUMBER',
                                'text' => (string) $btn['text'],
                                'phoneNumber' => (string) ($btn['url'] ?? $btn['phone'] ?? ''),
                            ];
                        }

                        return [
                            'type' => 'QUICK_REPLY',
                            'text' => (string) $btn['text'],
                        ];
                    })
                    ->values()
                    ->all();

                if (! empty($cardButtons)) {
                    $cardComponents[] = [
                        'type' => 'BUTTONS',
                        'buttons' => $cardButtons,
                    ];
                }

                return ['cardComponents' => $cardComponents];
            })
            ->values()
            ->all();

        $components = [];

        // WhatsApp carousel templates require a top-level BODY plus CAROUSEL cards.
        $intro = trim((string) ($carousel['body'] ?? $carousel['intro'] ?? ''));
        if ($intro === '') {
            $intro = ' ';
        }

        $components[] = [
            'type' => 'BODY',
            'text' => $intro,
            'format' => 'TEXT',
        ];

        $components[] = [
            'type' => 'CAROUSEL',
            'cards' => $cards,
        ];

        return $components;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isCamsSuccess(bool $httpOk, array $body): bool
    {
        if (! $httpOk) {
            return false;
        }

        $code = $body['Code'] ?? $body['code'] ?? null;
        if ($code === null || $code === '') {
            return true;
        }

        return in_array(strtoupper((string) $code), ['OK', '200', 'SUCCESS'], true);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractTemplateCode(array $body): string
    {
        $candidates = [
            $body['TemplateCode'] ?? null,
            $body['templateCode'] ?? null,
            data_get($body, 'Data.TemplateCode'),
            data_get($body, 'Data.templateCode'),
            data_get($body, 'data.TemplateCode'),
            data_get($body, 'data.templateCode'),
            data_get($body, 'body.Data.TemplateCode'),
            data_get($body, 'body.data.TemplateCode'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && CamsTemplateIdentity::isProviderCode($candidate)) {
                return $candidate;
            }
            if (is_numeric($candidate) && CamsTemplateIdentity::isProviderCode((string) $candidate)) {
                return (string) $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildButtons(array $payload): array
    {
        $buttons = collect($payload['buttons'] ?? [])
            ->filter(fn ($btn) => is_array($btn) && filled($btn['text'] ?? null))
            ->map(function (array $btn): array {
                $type = (string) ($btn['type'] ?? 'url');

                return match ($type) {
                    'url' => [
                        'type' => 'URL',
                        'text' => $btn['text'],
                        'url' => $btn['url'] ?? '',
                    ],
                    'phone' => [
                        'type' => 'PHONE_NUMBER',
                        'text' => $btn['text'],
                        'phoneNumber' => $btn['url'] ?? '',
                    ],
                    'unsubscribe' => [
                        'type' => 'URL',
                        'text' => $btn['text'],
                        'url' => $btn['url'] ?? '',
                    ],
                    'quick_reply' => [
                        'type' => 'QUICK_REPLY',
                        ...(((bool) ($payload['is_opt_out'] ?? false)) && strtolower(trim($btn['text'])) === 'stop promotions'
                            ? ['isOptOut' => true]
                            : ['text' => $btn['text']]),
                    ],
                    'flow' => [
                        'type' => 'FLOW',
                        'text' => $btn['text'],
                        'flowAction' => 'NAVIGATE',
                        'flowId' => $btn['flow_id'] ?? '',
                        'navigateScreen' => $btn['navigate_screen'] ?? '',
                    ],
                    'copy_code' => [
                        'type' => 'COPY_CODE',
                        'text' => $btn['text'],
                        'couponCode' => $btn['url'] ?? '',
                    ],
                    default => [
                        'type' => 'URL',
                        'text' => $btn['text'],
                        'url' => $btn['url'] ?? '',
                    ],
                };
            })
            ->values()
            ->all();

        return $buttons;
    }

    /**
     * Build example data for template body variables.
     *
     * @return array<string, mixed>
     */
    private function buildExample(Template $template): array
    {
        $payload = $template->wizardPayload();
        $samples = $payload['body']['samples'] ?? [];

        if (empty($samples)) {
            return [];
        }

        return ['body_text' => $samples];
    }

    /**
     * Upload local header/carousel sample media to CAMS OSS so Meta can download it.
     * Persists provider URLs back onto the template payload.
     */
    private function ensureProviderMediaUrls(Template $template, string $custSpaceId): void
    {
        $payload = $template->wizardPayload();
        $changed = false;

        $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];
        $headerType = (string) ($header['type'] ?? 'none');
        if (in_array($headerType, ['image', 'video', 'document'], true)) {
            $resolved = $this->resolveToProviderUrl(
                isset($header['media_url']) ? (string) $header['media_url'] : null,
                isset($header['media_path']) ? (string) $header['media_path'] : null,
                $custSpaceId,
                isset($header['doc_name']) ? (string) $header['doc_name'] : null,
            );
            if ($resolved !== null && $resolved !== ($header['media_url'] ?? null)) {
                $payload['header']['media_url'] = $resolved;
                $changed = true;
            }
        }

        $carousel = is_array($payload['carousel'] ?? null) ? $payload['carousel'] : [];
        if (($carousel['enabled'] ?? false) && is_array($carousel['cards'] ?? null)) {
            foreach ($carousel['cards'] as $index => $card) {
                if (! is_array($card)) {
                    continue;
                }
                $resolved = $this->resolveToProviderUrl(
                    isset($card['media_url']) ? (string) $card['media_url'] : null,
                    isset($card['media_path']) ? (string) $card['media_path'] : null,
                    $custSpaceId,
                    null,
                );
                if ($resolved !== null && $resolved !== ($card['media_url'] ?? null)) {
                    $payload['carousel']['cards'][$index]['media_url'] = $resolved;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $template->forceFill(['payload' => $payload])->save();
            $template->refresh();
        }
    }

    private function resolveToProviderUrl(?string $mediaUrl, ?string $mediaPath, string $custSpaceId, ?string $preferredName): ?string
    {
        $mediaUrl = filled($mediaUrl) ? trim((string) $mediaUrl) : null;
        $mediaPath = filled($mediaPath) ? trim((string) $mediaPath) : null;

        if ($mediaUrl !== null && $this->mediaUploader->isProviderHostedUrl($mediaUrl)) {
            return $mediaUrl;
        }

        if ($mediaPath !== null) {
            return $this->mediaUploader->uploadLocalPath($mediaPath, $custSpaceId, $preferredName);
        }

        // Already a public https URL the user pasted (carousel) — pass through.
        if ($mediaUrl !== null && preg_match('#^https://#i', $mediaUrl) === 1) {
            return $mediaUrl;
        }

        return $mediaUrl;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function headerMediaUrl(array $header): string
    {
        $url = trim((string) ($header['media_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $path = trim((string) ($header['media_path'] ?? ''));
        if ($path === '') {
            return '';
        }

        // Fallback only — prefer ensureProviderMediaUrls before submit.
        return app(TemplateMediaService::class)->absolutePublicUrl($path);
    }

    private function normalizeName(string $name): string
    {
        return str_replace(' ', '_', strtolower(trim($name)));
    }

    private function handleSubmissionError(Template $template, string $error): void
    {
        $previous = $template->status;
        $friendly = CamsComponentEncoder::friendlyError($error);

        $template->update([
            'status' => TemplateStatus::Rejected,
            'rejection_reason' => \Illuminate\Support\Str::limit($friendly, 500),
        ]);

        $this->logStatusChange($template, $previous, TemplateStatus::Rejected, $friendly);

        Log::error('Template submission failed', [
            'template_id' => $template->id,
            'error' => $error,
            'friendly' => $friendly,
        ]);
    }

    private function logStatusChange(Template $template, TemplateStatus $previous, TemplateStatus $current, ?string $reason = null): void
    {
        if ($previous === $current && $reason === null) {
            return;
        }

        TemplateStatusLog::query()->create([
            'template_id' => $template->id,
            'previous_status' => $previous->value,
            'new_status' => $current->value,
            'reason' => $reason,
            'meta' => ['name' => $template->name, 'code' => $template->code],
        ]);
    }
}
