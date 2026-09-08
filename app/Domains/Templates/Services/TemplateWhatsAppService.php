<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

class TemplateWhatsAppService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
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

        $components = $this->buildComponents($template);
        $name = $this->normalizeName($template->code ?: $template->name);
        $extra = ['CustSpaceId' => $line->alibaba_cust_space_id];

        // Add example data if body has variables
        $example = $this->buildExample($template);
        if (! empty($example)) {
            $extra['Example'] = json_encode($example, JSON_THROW_ON_ERROR);
        }

        // Authentication template TTL
        $payload = $template->wizardPayload();
        $auth = $payload['auth'] ?? [];
        if ($template->category === 'AUTHENTICATION' && ($auth['message_validity'] ?? false)) {
            $extra['MessageSendTtlSeconds'] = (string) ($auth['validity_seconds'] ?? 120);
        }

        try {
            $response = $this->camsClient->createChatappTemplate(
                $name,
                $template->language,
                TemplateCategoryCatalog::whatsAppCategory((string) $template->category),
                $components,
                $extra,
            );

            if ($response->successful()) {
                $body = $response->json();
                $templateCode = $body['TemplateCode'] ?? $body['templateCode'] ?? '';

                $previous = $template->status;
                $template->update([
                    'code' => $templateCode ?: $template->code,
                    'status' => TemplateStatus::PendingReview,
                    'synced_at' => now(),
                    'rejection_reason' => null,
                ]);

                $this->logStatusChange($template, $previous, TemplateStatus::PendingReview);

                return true;
            }

            $this->handleSubmissionError($template, $response->body());

            return false;
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
        if (! $template->code) {
            return $this->submitTemplate($template);
        }

        if (! $this->camsClient->isConfigured()) {
            return false;
        }

        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            return false;
        }

        $components = $this->buildComponents($template);
        $name = $this->normalizeName($template->code ?: $template->name);
        $extra = ['CustSpaceId' => $line->alibaba_cust_space_id];

        try {
            $response = $this->camsClient->modifyChatappTemplate(
                $template->code,
                $name,
                $template->language,
                TemplateCategoryCatalog::whatsAppCategory((string) $template->category),
                $components,
                $extra,
            );

            if ($response->successful()) {
                $previous = $template->status;
                $template->update([
                    'status' => TemplateStatus::PendingReview,
                    'synced_at' => now(),
                    'rejection_reason' => null,
                ]);

                $this->logStatusChange($template, $previous, TemplateStatus::PendingReview);

                return true;
            }

            $this->handleSubmissionError($template, $response->body());

            return false;
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

        $carousel = $payload['carousel'] ?? [];
        if ($carousel['enabled'] ?? false) {
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
                'url' => $header['media_url'] ?? ($header['media_path'] ? asset('storage/'.$header['media_path']) : ''),
            ];
        } elseif ($headerType === 'video') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'VIDEO',
                'url' => $header['media_url'] ?? ($header['media_path'] ? asset('storage/'.$header['media_path']) : ''),
            ];
        } elseif ($headerType === 'document') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'DOCUMENT',
                'url' => $header['media_url'] ?? ($header['media_path'] ? asset('storage/'.$header['media_path']) : ''),
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
            ->filter(fn ($card) => ! empty($card['body']))
            ->map(function (array $card): array {
                $cardComponent = [
                    'headerType' => strtoupper($card['header'] ?? 'IMAGE'),
                    'bodyText' => $card['body'] ?? '',
                ];

                if (! empty($card['media_url'])) {
                    $cardComponent['mediaUrl'] = $card['media_url'];
                }

                $cardButtons = collect($card['buttons'] ?? [])
                    ->filter(fn ($btn) => ! empty($btn['text']))
                    ->map(fn ($btn) => [
                        'type' => $btn['type'] ?? 'QUICK_REPLY',
                        'text' => $btn['text'],
                    ])
                    ->values()
                    ->all();

                if (! empty($cardButtons)) {
                    $cardComponent['buttons'] = $cardButtons;
                }

                return $cardComponent;
            })
            ->values()
            ->all();

        return [[
            'type' => 'CAROUSEL',
            'cards' => $cards,
        ]];
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

    private function normalizeName(string $name): string
    {
        return str_replace(' ', '_', strtolower(trim($name)));
    }

    private function handleSubmissionError(Template $template, string $error): void
    {
        $previous = $template->status;

        $template->update([
            'status' => TemplateStatus::Rejected,
            'rejection_reason' => \Illuminate\Support\Str::limit($error, 500),
        ]);

        $this->logStatusChange($template, $previous, TemplateStatus::Rejected, $error);

        Log::error('Template submission failed', [
            'template_id' => $template->id,
            'error' => $error,
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
