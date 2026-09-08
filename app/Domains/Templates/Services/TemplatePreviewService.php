<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Models\Template;

class TemplatePreviewService
{
    public function __construct(
        private readonly TemplateRegistryService $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCode(string $code): array
    {
        $template = $this->registry->findByCode($code);

        if (! $template instanceof Template) {
            return [
                'title' => $code,
                'body' => 'Template preview unavailable.',
                'footer' => '',
                'header_type' => 'none',
                'header_text' => '',
                'header_video' => null,
                'header_document' => null,
                'body_samples' => [],
                'buttons' => [],
                'button_mode' => 'call_to_action',
                'is_opt_out' => false,
                'header_image' => null,
                'rejection_reason' => null,
                'raw_body' => 'Template preview unavailable.',
                'variables' => [],
            ];
        }

        return $this->forTemplate($template);
    }

    /**
     * @param  array<string, scalar|null>  $variableValues
     * @return array<string, mixed>
     */
    public function forTemplate(Template $template, array $variableValues = [], bool $keepPlaceholders = false): array
    {
        $payload = $template->wizardPayload();
        $buttonMode = (string) ($payload['button_mode'] ?? 'call_to_action');
        $isOptOut = (bool) ($payload['is_opt_out'] ?? false);

        $buttons = collect($payload['buttons'] ?? [])
            ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? null))
            ->map(fn (array $button): array => [
                'text' => (string) $button['text'],
                'type' => (string) ($button['type'] ?? 'url'),
                'url' => (string) ($button['url'] ?? ''),
                'flow_id' => (string) ($button['flow_id'] ?? ''),
            ])
            ->values()
            ->all();

        // Resolve footer text — opt-out footer overrides if enabled
        $footerText = trim((string) ($payload['footer']['text'] ?? ''));
        if ($isOptOut && $footerText === '') {
            $footerText = 'Not interested? Tap Stop promotions';
        }

        $headerImage = null;
        $headerVideo = null;
        $headerDocument = null;
        $headerType = (string) ($payload['header']['type'] ?? 'none');
        $mediaPath = $payload['header']['media_path'] ?? null;
        $mediaUrl = $payload['header']['media_url'] ?? null;

        if ($headerType === 'image' && (filled($mediaPath) || filled($mediaUrl))) {
            $headerImage = filled($mediaUrl) ? $mediaUrl : ($mediaPath ? asset('storage/'.$mediaPath) : null);
        }
        if ($headerType === 'video' && (filled($mediaPath) || filled($mediaUrl))) {
            $headerVideo = filled($mediaUrl) ? $mediaUrl : ($mediaPath ? asset('storage/'.$mediaPath) : null);
        }
        if ($headerType === 'document' && (filled($mediaPath) || filled($mediaUrl))) {
            $headerDocument = filled($mediaUrl) ? $mediaUrl : ($mediaPath ? asset('storage/'.$mediaPath) : null);
        }

        // Authentication template preview data
        $auth = $payload['auth'] ?? [];
        if (($auth['enabled'] ?? false) || $template->category === 'AUTHENTICATION') {
            $buttonMode = 'authentication';
        }

        // LTO preview data
        $lto = $payload['lto'] ?? [];
        if (($lto['enabled'] ?? false) || $template->category === 'LIMITED_TIME_OFFER') {
            $buttonMode = 'lto';
        }

        // Carousel preview data
        $carousel = $payload['carousel'] ?? [];

        $rawBody = (string) ($payload['body']['text'] ?? '');
        if ($rawBody === '') {
            $rawBody = (string) ($template->body_preview ?: $template->name);
        }
        $rawHeaderText = (string) ($payload['header']['text'] ?? '');
        $samples = array_values($payload['body']['samples'] ?? []);
        $variables = $this->variablesForTemplate($template);
        $names = array_column($variables, 'name');

        if ($keepPlaceholders) {
            $body = TemplateVariableSyntax::previewText($rawBody);
            $footer = TemplateVariableSyntax::previewText($footerText);
            $headerText = TemplateVariableSyntax::previewText($rawHeaderText);
        } else {
            $resolvedValues = array_merge(
                TemplateVariableSyntax::mapSamplesToNames($names, $samples),
                $this->normalizeVariableValues($variableValues, $variables),
            );
            $body = TemplateVariableSyntax::substitute($rawBody, $resolvedValues);
            $footer = TemplateVariableSyntax::substitute($footerText, $resolvedValues);
            $headerText = TemplateVariableSyntax::substitute($rawHeaderText, $resolvedValues);
        }

        return [
            'title' => $template->name,
            'body' => $body,
            'raw_body' => TemplateVariableSyntax::previewText($rawBody),
            'footer' => $footer,
            'header_type' => $headerType,
            'header_text' => $headerText,
            'header_video' => $headerVideo,
            'header_document' => $headerDocument,
            'body_samples' => $samples,
            'buttons' => $buttons,
            'button_mode' => $buttonMode,
            'is_opt_out' => $isOptOut,
            'header_image' => $headerImage,
            'rejection_reason' => $template->rejection_reason,
            'carousel_cards' => $carousel['cards'] ?? [],
            'lto' => $lto,
            'auth' => $auth,
            'variables' => $variables,
        ];
    }

    /**
     * Whether the template body/header/footer contains $(variable) placeholders.
     */
    public function templateHasVariables(Template $template): bool
    {
        return count($this->variablesForTemplate($template)) > 0;
    }

    /**
     * Variables for campaign mapping: body/header placeholders + linked pivot rows.
     *
     * @return list<array{id: int|null, name: string, type: string, type_label: string}>
     */
    public function variablesForTemplate(Template $template): array
    {
        $template->loadMissing('variables');

        $payload = $template->wizardPayload();
        $text = implode("\n", array_filter([
            (string) ($payload['header']['text'] ?? ''),
            (string) ($payload['body']['text'] ?? $template->body_preview ?? ''),
            (string) ($payload['footer']['text'] ?? ''),
        ]));

        $namesFromText = TemplateVariableSyntax::extractVariableNames($text);
        $linked = $template->variables->keyBy('name');

        $orderedNames = collect($namesFromText)
            ->merge($linked->keys())
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        return $orderedNames->map(function (string $name) use ($linked): array {
            $variable = $linked->get($name);

            return [
                'id' => $variable?->id,
                'name' => $name,
                'type' => $variable?->type?->value ?? 'dynamic',
                'type_label' => $variable?->type?->label() ?? 'Dynamic',
            ];
        })->all();
    }

    /**
     * Accept values keyed by variable name or (legacy) variable id.
     *
     * @param  array<string|int, mixed>  $variableValues
     * @param  list<array{id: int|null, name: string}>  $variables
     * @return array<string, string>
     */
    private function normalizeVariableValues(array $variableValues, array $variables): array
    {
        $byId = [];
        foreach ($variables as $variable) {
            if (($variable['id'] ?? null) !== null) {
                $byId[(string) $variable['id']] = $variable['name'];
            }
        }

        $normalized = [];
        foreach ($variableValues as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $name = $byId[(string) $key] ?? (is_string($key) ? $key : null);
            if (! is_string($name) || $name === '') {
                continue;
            }

            $normalized[$name] = (string) ($value ?? '');
        }

        return $normalized;
    }
}
