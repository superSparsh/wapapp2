<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Domains\Templates\Support\WhatsAppTextFormatter;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Domains\WhatsApp\Services\CamsTemplateMediaUploader;
use App\Domains\WhatsApp\Support\CamsComponentEncoder;
use App\Domains\WhatsApp\Support\CamsErrorPresenter;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\Variable;
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
            $this->handleSubmissionError(
                $template,
                'WhatsApp / Alibaba CAMS is not configured on this server (missing access keys).',
            );

            return false;
        }

        $line = $this->resolveLineForSubmit($template);
        if (! $line instanceof WhatsappLine) {
            Log::warning('No WhatsApp line configured for template submission.', ['template_id' => $template->id]);
            $this->handleSubmissionError(
                $template,
                'WhatsApp Phone Number is not connected (missing Alibaba CustSpaceId). Connect the number in Integration, then resubmit.',
            );

            return false;
        }

        // If we already have a real CAMS TemplateCode, modify instead of create.
        if (filled($template->whatsappCode())) {
            return $this->modifyTemplate($template);
        }

        // Same name may already exist on Meta (soft-deleted locally, or created outside).
        // Prefer modify over create to avoid Meta "language is being deleted" / duplicate errors.
        $existingCode = $this->resolveExistingProviderCode($template, $line);
        if ($existingCode !== null) {
            $template->forceFill(['code' => $existingCode])->save();

            return $this->modifyTemplate($template->refresh());
        }

        $name = $this->normalizeName($template->name);
        $extra = ['CustSpaceId' => $line->alibaba_cust_space_id];

        // Legacy parity: named variable samples for body/header/buttons ($(var) → Example map).
        $example = $this->buildExample($template);
        if (! empty($example)) {
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
            if ($bodyError = $this->validateComponentsForSubmit($template, $components)) {
                $this->handleSubmissionError($template, $bodyError);

                return false;
            }
            Log::info('Submitting CreateChatappTemplate', [
                'template_id' => $template->id,
                'header_url' => collect($components)->firstWhere('type', 'HEADER')['url'] ?? null,
                'example_keys' => array_keys($example),
            ]);
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
                'source' => TemplateSource::Cams,
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
            $this->handleSubmissionError(
                $template,
                'WhatsApp / Alibaba CAMS is not configured on this server (missing access keys).',
            );

            return false;
        }

        $line = $this->resolveLineForSubmit($template);
        if (! $line instanceof WhatsappLine) {
            Log::warning('No WhatsApp line configured for template modify.', ['template_id' => $template->id]);
            $this->handleSubmissionError(
                $template,
                'WhatsApp Phone Number is not connected (missing Alibaba CustSpaceId). Connect the number in Integration, then resubmit.',
            );

            return false;
        }

        $name = $this->normalizeName($template->name);
        $extra = ['CustSpaceId' => $line->alibaba_cust_space_id];

        // Legacy parity: named variable samples (body + header + buttons). Required when
        // HEADER text/media contains $(var) — Meta rejects with "HEADER missing example".
        $example = $this->buildExample($template);
        if (! empty($example)) {
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
            if ($bodyError = $this->validateComponentsForSubmit($template, $components)) {
                $this->handleSubmissionError($template, $bodyError);

                return false;
            }
            Log::info('Submitting ModifyChatappTemplate', [
                'template_id' => $template->id,
                'header_url' => collect($components)->firstWhere('type', 'HEADER')['url'] ?? null,
                'example_keys' => array_keys($example),
            ]);
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
                'source' => TemplateSource::Cams,
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
            return $this->deleteFromCamsWithFallbacks($template, $line, $code);
        } catch (\Throwable $e) {
            Log::error('Failed to delete template from WhatsApp', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function deleteFromCamsWithFallbacks(Template $template, WhatsappLine $line, string $code): bool
    {
        $custSpaceId = (string) $line->alibaba_cust_space_id;
        $language = $this->resolveDeleteLanguage($template, $line, $code);
        $templateName = $this->normalizeName((string) $template->name);

        /** @var list<array<string, string>> $attempts */
        $attempts = [
            // Legacy production: TemplateCode + CustSpaceId only.
            [
                'TemplateCode' => $code,
                'CustSpaceId' => $custSpaceId,
            ],
            // Name + language (Alibaba docs: TemplateType required when Language is set).
            [
                'TemplateName' => $templateName,
                'Language' => $language,
                'TemplateType' => 'WHATSAPP',
                'CustSpaceId' => $custSpaceId,
            ],
            [
                'TemplateCode' => $code,
                'Language' => $language,
                'TemplateType' => 'WHATSAPP',
                'CustSpaceId' => $custSpaceId,
            ],
        ];

        $lastBody = [];
        foreach ($attempts as $index => $params) {
            $response = $this->camsClient->deleteChatappTemplate($params);
            $body = $response->json() ?? [];
            $lastBody = is_array($body) ? $body : [];

            if ($this->isCamsSuccess($response->successful(), $lastBody)) {
                Log::info('WhatsApp template deleted from CAMS', [
                    'template_id' => $template->id,
                    'attempt' => $index + 1,
                    'params' => array_keys($params),
                ]);

                return true;
            }

            Log::info('WhatsApp template delete attempt failed', [
                'template_id' => $template->id,
                'attempt' => $index + 1,
                'params' => array_keys($params),
                'response' => $response->body(),
            ]);
        }

        Log::warning('WhatsApp template deletion failed after all attempts', [
            'template_id' => $template->id,
            'template_code' => $code,
            'last_response' => $lastBody,
        ]);

        return false;
    }

    private function resolveDeleteLanguage(Template $template, WhatsappLine $line, string $code): string
    {
        $fallback = CamsTemplateIdentity::language($template->language);

        try {
            $response = $this->camsClient->getChatappTemplateDetail([
                'TemplateCode' => $code,
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'Language' => $fallback,
            ]);

            if (! $response->successful()) {
                return $fallback;
            }

            $body = $response->json() ?? [];
            if (! is_array($body)) {
                return $fallback;
            }

            $data = $body['Data'] ?? $body['data'] ?? $body;
            if (! is_array($data)) {
                return $fallback;
            }

            $remote = $data['Language'] ?? $data['language'] ?? null;

            return is_string($remote) && $remote !== '' ? $remote : $fallback;
        } catch (\Throwable) {
            return $fallback;
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
     * Guard against CAMS InvalidParameter ("Message must not be null") when BODY Text is missing.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    private function validateComponentsForSubmit(Template $template, array $components): ?string
    {
        if (TemplateCategoryCatalog::isAuthentication((string) $template->category)) {
            return null;
        }

        $body = collect($components)->first(function (mixed $component): bool {
            if (! is_array($component)) {
                return false;
            }

            $type = strtoupper((string) ($component['type'] ?? $component['Type'] ?? ''));

            return $type === 'BODY';
        });

        if (! is_array($body)) {
            return json_encode([
                'Code' => 'InvalidParameter',
                'Message' => 'Message must not be null',
            ], JSON_THROW_ON_ERROR);
        }

        $text = trim((string) ($body['text'] ?? $body['Text'] ?? ''));
        if ($text === '') {
            return json_encode([
                'Code' => 'InvalidParameter',
                'Message' => 'Message must not be null',
            ], JSON_THROW_ON_ERROR);
        }

        return null;
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
            'text' => WhatsAppTextFormatter::toWhatsAppApi((string) ($payload['body']['text'] ?? '')),
            'format' => 'TEXT',
        ];

        // LTO component
        $lto = $payload['lto'] ?? [];
        if ($lto['enabled'] ?? false) {
            $components[] = [
                'type' => 'LIMITED_TIME_OFFER',
                'text' => WhatsAppTextFormatter::toWhatsAppApi((string) ($lto['discount_introduction'] ?? '')),
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
                'text' => WhatsAppTextFormatter::toWhatsAppApi((string) ($header['text'] ?? '')),
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

        // FOOTER (skip for opt-out - WhatsApp handles it via isOptOut flag)
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
        $isLto = TemplateCategoryCatalog::isLto((string) $template->category)
            || (bool) ($lto['enabled'] ?? false);
        $buttons = $this->buildButtons($payload, $isLto);
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
        $autoFill = (bool) ($auth['auto_fill'] ?? false);
        $zeroTap = (bool) ($auth['zero_tap'] ?? false);

        $buttonType = 'COPY_CODE';
        if ($autoFill) {
            $buttonType = $zeroTap ? 'ZERO_TAP' : 'ONE_TAP';
        }

        $button = [
            'type' => $buttonType,
            'text' => $autoFill
                ? 'Copy Code'
                : $this->normalizeAuthButtonLabel((string) ($auth['copy_button_text'] ?? 'Copy Code')),
            'autofillText' => $this->normalizeAuthButtonLabel((string) ($auth['fill_button_text'] ?? 'Autofill')),
        ];

        if ($zeroTap) {
            $button['zeroTapTermsAccepted'] = true;
        }

        $supportedApps = $auth['supported_apps'] ?? [];
        if ($autoFill && ! empty($supportedApps)) {
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
                        'text' => WhatsAppTextFormatter::toWhatsAppApi((string) ($card['body'] ?? '')),
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
            'text' => WhatsAppTextFormatter::toWhatsAppApi($intro),
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
     * Legacy parity: light normalization for auth OTP button labels.
     */
    private function normalizeAuthButtonLabel(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $normalized = trim($text);

        return $normalized !== '' ? $normalized : 'copy code';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildButtons(array $payload, bool $isLto = false): array
    {
        $buttons = collect($payload['buttons'] ?? [])
            ->filter(fn ($btn) => is_array($btn) && filled($btn['text'] ?? null))
            ->map(function (array $btn) use ($isLto, $payload): array {
                $type = (string) ($btn['type'] ?? 'url');

                return match ($type) {
                    'url' => [
                        'type' => 'URL',
                        'text' => $btn['text'],
                        'url' => $btn['url'] ?? '',
                    ],
                    'phone' => $isLto ? [
                        'type' => 'URL',
                        'text' => $btn['text'],
                        'url' => $btn['url'] ?? '',
                    ] : [
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
                        'text' => $btn['text'],
                        ...(((bool) ($payload['is_opt_out'] ?? false)) && strtolower(trim((string) $btn['text'])) === 'stop promotions'
                            ? ['isOptOut' => true]
                            : []),
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
     * Build CAMS Example map: {variable_name: sample_value}.
     *
     * Legacy / Alibaba expect named samples for every $(var) in body, header text,
     * dynamic header media URL, footer, and button URLs. Meta rejects HEADER with
     * variables when the matching example is missing.
     *
     * @return array<string, string>
     */
    private function buildExample(Template $template): array
    {
        $payload = $template->wizardPayload();
        $template->loadMissing('variables');

        $headerText = (string) ($payload['header']['text'] ?? '');
        $headerMediaUrl = (string) ($payload['header']['media_url'] ?? '');
        $bodyText = (string) ($payload['body']['text'] ?? $template->body_preview ?? '');
        $footerText = (string) ($payload['footer']['text'] ?? '');

        $chunks = [$headerText, $headerMediaUrl, $bodyText, $footerText];
        foreach (is_array($payload['buttons'] ?? null) ? $payload['buttons'] : [] as $button) {
            if (! is_array($button)) {
                continue;
            }
            $chunks[] = (string) ($button['url'] ?? '');
            $chunks[] = (string) ($button['text'] ?? '');
        }

        $names = collect($chunks)
            ->flatMap(fn (string $chunk) => TemplateVariableSyntax::extractVariableNames($chunk))
            ->merge($template->variables->pluck('name'))
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $bodyNames = TemplateVariableSyntax::extractVariableNames($bodyText);
        $bodySamples = is_array($payload['body']['samples'] ?? null)
            ? array_values($payload['body']['samples'])
            : [];
        $bodySampleMap = TemplateVariableSyntax::mapSamplesToNames($bodyNames, $bodySamples);

        $headerMediaNames = TemplateVariableSyntax::extractVariableNames($headerMediaUrl);
        $linked = $template->variables->keyBy('name');

        $examples = [];
        foreach ($names as $name) {
            /** @var Variable|null $variable */
            $variable = $linked->get($name);
            $stored = filled($variable?->value) ? trim((string) $variable->value) : '';
            if ($stored !== '') {
                $examples[$name] = $stored;

                continue;
            }

            $bodySample = trim((string) ($bodySampleMap[$name] ?? ''));
            if ($bodySample !== '') {
                $examples[$name] = $bodySample;

                continue;
            }

            $dataType = $variable?->data_type;
            $isHeaderMedia = in_array($name, $headerMediaNames, true);
            $examples[$name] = $this->sampleValueForVariable($name, $dataType, $isHeaderMedia);
        }

        return $examples;
    }

    /**
     * Legacy-style fallback sample for a named variable.
     */
    private function sampleValueForVariable(string $name, ?VariableDataType $dataType, bool $isHeaderMedia): string
    {
        if ($dataType instanceof VariableDataType) {
            return match ($dataType) {
                VariableDataType::Number => (string) random_int(1000, 9999),
                VariableDataType::Url => 'https://example.com',
                VariableDataType::Image => 'https://whatsapp-prod-oss-bucket.oss-ap-southeast-1.aliyuncs.com/5131327716897960/rc-upload-1716962644738-2.jpg',
                VariableDataType::Video => 'https://wapapp.tittu.in/upload/videos/1756437199_68b11acf3ee96.mp4',
                VariableDataType::Pdf => 'https://wapapp.tittu.in/upload/docs/1756129645.pdf',
                VariableDataType::String => 'Sample',
            };
        }

        $lower = strtolower($name);
        if ($isHeaderMedia || str_contains($lower, 'image') || str_contains($lower, 'img') || str_contains($lower, 'photo')) {
            return 'https://whatsapp-prod-oss-bucket.oss-ap-southeast-1.aliyuncs.com/5131327716897960/rc-upload-1716962644738-2.jpg';
        }
        if (str_contains($lower, 'video')) {
            return 'https://wapapp.tittu.in/upload/videos/1756437199_68b11acf3ee96.mp4';
        }
        if (str_contains($lower, 'doc') || str_contains($lower, 'pdf')) {
            return 'https://wapapp.tittu.in/upload/docs/1756129645.pdf';
        }
        if (str_contains($lower, 'url') || str_contains($lower, 'link')) {
            return 'https://example.com';
        }

        return 'Sample';
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
        $headerType = strtolower((string) ($header['type'] ?? 'none'));
        if (in_array($headerType, ['image', 'img', 'video', 'document', 'doc'], true)) {
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

        // Always re-upload local files. Cached media_url may be localhost / auth-gated / private OSS.
        if ($mediaPath !== null) {
            return $this->mediaUploader->uploadLocalPath($mediaPath, $custSpaceId, $preferredName);
        }

        if ($mediaUrl !== null && $this->mediaUploader->isProviderHostedUrl($mediaUrl)) {
            return $mediaUrl;
        }

        // External URL the user pasted - CAMS must be able to download it as-is.
        if ($mediaUrl !== null && preg_match('#^https://#i', $mediaUrl) === 1) {
            return $mediaUrl;
        }

        // Dynamic header media ($(variable_name)) - pass through to CAMS like legacy.
        if ($mediaUrl !== null && (str_starts_with($mediaUrl, '$(') || str_contains($mediaUrl, '$('))) {
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

        // Fallback only - prefer ensureProviderMediaUrls before submit.
        return app(TemplateMediaService::class)->absolutePublicUrl($path);
    }

    private function normalizeName(string $name): string
    {
        return str_replace(' ', '_', strtolower(trim($name)));
    }

    /**
     * Prefer the template's line; if CustSpaceId is missing, attach the default connected line.
     */
    private function resolveLineForSubmit(Template $template): ?WhatsappLine
    {
        $template->loadMissing('whatsappLine');
        $line = $template->whatsappLine;

        if ($line instanceof WhatsappLine && filled($line->alibaba_cust_space_id)) {
            return $line;
        }

        $fallback = WhatsappLine::query()
            ->whereNotNull('alibaba_cust_space_id')
            ->where('alibaba_cust_space_id', '!=', '')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $fallback instanceof WhatsappLine) {
            return null;
        }

        if ((int) ($template->whatsapp_line_id ?? 0) !== (int) $fallback->id) {
            $template->forceFill(['whatsapp_line_id' => $fallback->id])->saveQuietly();
            $template->setRelation('whatsappLine', $fallback);
        }

        return $fallback;
    }

    /**
     * Find an existing CAMS TemplateCode for this local draft (soft-deleted archive or remote list).
     */
    private function resolveExistingProviderCode(Template $template, WhatsappLine $line): ?string
    {
        $archived = Template::onlyTrashed()
            ->where('whatsapp_line_id', $line->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $template->name))])
            ->orderByDesc('deleted_at')
            ->get(['payload']);

        foreach ($archived as $row) {
            $code = data_get($row->payload, 'meta.archived_code');
            if (CamsTemplateIdentity::isProviderCode(is_string($code) ? $code : null)) {
                return (string) $code;
            }
        }

        if (! $this->camsClient->isConfigured() || blank($line->alibaba_cust_space_id)) {
            return null;
        }

        try {
            $response = $this->camsClient->listTemplates([
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'Name' => $this->normalizeName((string) $template->name),
                'Language' => CamsTemplateIdentity::language($template->language),
                'PageSize' => '10',
                'PageIndex' => '1',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json() ?? [];
            if (! is_array($body)) {
                return null;
            }

            $list = $body['List']
                ?? $body['Data']['List']
                ?? $body['data']['list']
                ?? $body['TemplateList']
                ?? [];

            if (! is_array($list)) {
                return null;
            }

            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $code = $item['TemplateCode'] ?? $item['templateCode'] ?? $item['Code'] ?? null;
                if (CamsTemplateIdentity::isProviderCode(is_string($code) ? $code : null)) {
                    return (string) $code;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve existing CAMS template code before create', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function handleSubmissionError(Template $template, string $error): void
    {
        $previous = $template->status;
        // Legacy last_status: store the cleaned CAMS/Meta Message, not a rewritten UI title.
        $stored = CamsErrorPresenter::legacyDisplayReason($error) ?? '';

        if ($stored === '') {
            $decoded = json_decode($error, true);
            if (is_array($decoded)) {
                $fallback = trim((string) (
                    $decoded['Message']
                    ?? $decoded['message']
                    ?? $decoded['Reason']
                    ?? $decoded['reason']
                    ?? data_get($decoded, 'Error.Message')
                    ?? data_get($decoded, 'Data.Reason')
                    ?? data_get($decoded, 'data.reason')
                    ?? data_get($decoded, 'Data.Message')
                    ?? data_get($decoded, 'data.Message')
                    ?? data_get($decoded, 'AccessDeniedDetail')
                    ?? ''
                ));
                $code = trim((string) (
                    $decoded['Code']
                    ?? $decoded['code']
                    ?? data_get($decoded, 'Error.Code')
                    ?? data_get($decoded, 'Data.Code')
                    ?? ''
                ));
                if ($fallback !== '' && ! CamsErrorPresenter::isEmptyProviderReason($fallback)
                    && ! in_array(strtolower($fallback), ['ok', 'success', 'successful'], true)
                ) {
                    $stored = $code !== '' ? $fallback.' ('.$code.')' : $fallback;
                } elseif ($code !== '' && ! CamsErrorPresenter::isEmptyProviderReason($code)) {
                    $stored = $code;
                }
            }
        }

        if ($stored === '' || CamsErrorPresenter::isGenericFiller($stored)) {
            $raw = trim(preg_replace('/\s+/', ' ', $error) ?? $error);
            $stored = $raw !== ''
                ? \Illuminate\Support\Str::limit($raw, 2000)
                : 'WhatsApp provider returned an error without a readable message.';
        }

        $stored = CamsErrorPresenter::legacyDisplayReason($stored) ?? $stored;

        $presented = CamsComponentEncoder::presentError($stored);

        $template->update([
            'status' => TemplateStatus::Rejected,
            'rejection_reason' => \Illuminate\Support\Str::limit($stored, 2000),
        ]);

        $this->logStatusChange($template, $previous, TemplateStatus::Rejected, $stored);

        Log::error('Template submission failed', [
            'template_id' => $template->id,
            'error' => \Illuminate\Support\Str::limit($error, 2000),
            'stored' => $stored,
            'title' => $presented['title'],
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
