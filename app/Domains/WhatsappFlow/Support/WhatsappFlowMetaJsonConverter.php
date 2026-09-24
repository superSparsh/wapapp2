<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Support;

/**
 * Converts the internal builder JSON schema to Meta WhatsApp Flow JSON v6.3.
 *
 * Payload rules (aligned with legacy EditFlow):
 * - Footer payload must always be a JSON object (`{}`), never an array (`[]`).
 * - Only interactive input fields belong in payload (not headings / images / body text).
 * - Navigate: current-screen inputs only. Complete: all screens' inputs.
 */
final class WhatsappFlowMetaJsonConverter
{
    private const VERSION = '6.3';

    /** @var list<string> */
    private const INPUT_TYPES = [
        'text',
        'email',
        'phone',
        'number',
        'password',
        'passcode',
        'paragraph',
        'textarea',
        'date',
        'date_picker',
        'radio',
        'single_choice',
        'checkbox',
        'multi_choice',
        'dropdown',
        'opt-in',
        'opt_in',
    ];

    /**
     * @param  array<string, mixed>  $flowJson
     * @return array<string, mixed>
     */
    public static function convert(array $flowJson): array
    {
        $screens = collect($flowJson['screens'] ?? [])
            ->filter(fn ($screen) => is_array($screen))
            ->values();

        if ($screens->isEmpty()) {
            return [
                'version' => self::VERSION,
                'routing_model' => new \stdClass,
                'screens' => [],
            ];
        }

        $routingModel = [];
        foreach ($screens as $index => $screen) {
            $screenId = self::screenId($screen, $index);
            $routingModel[$screenId] = self::resolveNextScreenIds($screen, $index, $screens);
        }

        $metaScreens = $screens->map(function (array $screen, int $index) use ($screens): array {
            $screenId = self::screenId($screen, $index);
            $isLast = self::isTerminalScreen($screen, $index, $screens);
            $fields = collect($screen['fields'] ?? [])->filter(fn ($f) => is_array($f))->values();
            $contentFields = $fields->filter(fn (array $f) => ($f['type'] ?? '') !== 'footer');
            $footer = $fields->firstWhere('type', 'footer');

            $children = $contentFields
                ->map(fn (array $field, int $fieldIndex) => self::mapField($field, $fieldIndex))
                ->filter()
                ->values()
                ->all();

            if ($footer !== null) {
                $children[] = self::mapFooter($footer, $screenId, $index, $screens);
            } else {
                $children[] = self::defaultFooter($screenId, $index, $screens, $isLast);
            }

            $metaScreen = [
                'id' => $screenId,
                'title' => (string) ($screen['title'] ?? 'Screen '.($index + 1)),
                'layout' => [
                    'type' => 'SingleColumnLayout',
                    'children' => [
                        [
                            'type' => 'Form',
                            'name' => 'flow_path',
                            'children' => $children,
                        ],
                    ],
                ],
            ];

            if ($isLast) {
                $metaScreen['terminal'] = true;
                $metaScreen['success'] = true;
            }

            return $metaScreen;
        })->all();

        return [
            'version' => self::VERSION,
            'routing_model' => $routingModel,
            'screens' => $metaScreens,
        ];
    }

    /**
     * @param  array<string, mixed>  $screen
     */
    private static function screenId(array $screen, int $index): string
    {
        $id = (string) ($screen['id'] ?? '');

        return $id !== '' ? $id : 'SCREEN_'.($index + 1);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $screens
     * @return list<string>
     */
    private static function resolveNextScreenIds(
        array $screen,
        int $index,
        \Illuminate\Support\Collection $screens,
    ): array {
        $nextId = $screen['next_screen'] ?? null;

        if (is_string($nextId) && $nextId !== '') {
            return [$nextId];
        }

        $next = $screens->get($index + 1);

        return $next !== null ? [self::screenId($next, $index + 1)] : [];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $screens
     */
    private static function isTerminalScreen(
        array $screen,
        int $index,
        \Illuminate\Support\Collection $screens,
    ): bool {
        $nextIds = self::resolveNextScreenIds($screen, $index, $screens);

        return $nextIds === [];
    }

    private static function isInputField(array $field): bool
    {
        $type = (string) ($field['type'] ?? '');

        return in_array($type, self::INPUT_TYPES, true);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>|null
     */
    private static function mapField(array $field, int $fieldIndex): ?array
    {
        $type = (string) ($field['type'] ?? 'text');
        $label = (string) ($field['label'] ?? $field['name'] ?? 'Field');
        $name = self::fieldName($field, $fieldIndex);
        $required = (bool) ($field['required'] ?? false);
        $helperText = isset($field['helper_text']) && filled($field['helper_text']) ? (string) $field['helper_text'] : null;

        return match ($type) {
            'text' => array_filter([
                'type' => 'TextInput',
                'input-type' => 'text',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),

            'email' => array_filter([
                'type' => 'TextInput',
                'input-type' => 'email',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),

            // Legacy EditFlow: phone as text + 10-digit pattern (Meta validates more reliably).
            'phone' => array_filter([
                'type' => 'TextInput',
                'input-type' => 'text',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'pattern' => '^[0-9]{10}$',
                'helper-text' => $helperText ?? 'Enter 10 digits only',
            ], fn ($v) => $v !== null),

            'number' => array_filter([
                'type' => 'TextInput',
                'input-type' => 'number',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),

            'password', 'passcode' => array_filter([
                'type' => 'TextInput',
                'input-type' => 'password',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),

            'paragraph', 'textarea' => array_filter([
                'type' => 'TextArea',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),

            'date', 'date_picker' => array_filter([
                'type' => 'DatePicker',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),

            'radio', 'single_choice' => array_filter([
                'type' => 'RadioButtonsGroup',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'data-source' => self::options($field),
                'description' => $helperText,
            ], fn ($v) => $v !== null),

            'checkbox', 'multi_choice' => array_filter([
                'type' => 'CheckboxGroup',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'data-source' => self::options($field),
                'description' => $helperText,
            ], fn ($v) => $v !== null),

            'dropdown' => array_filter([
                'type' => 'Dropdown',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'data-source' => self::options($field),
            ], fn ($v) => $v !== null),

            'opt-in', 'opt_in' => [
                'type' => 'OptIn',
                'label' => $label,
                'name' => $name,
                'required' => $required,
            ],

            'large-heading', 'heading' => [
                'type' => 'TextHeading',
                'text' => (string) ($field['text'] ?? $label),
            ],

            'small-heading', 'subheading' => [
                'type' => 'TextSubheading',
                'text' => (string) ($field['text'] ?? $label),
            ],

            'caption' => [
                'type' => 'TextCaption',
                'text' => (string) ($field['text'] ?? $label),
            ],

            'image' => self::mapImage($field),

            'text-display', 'body' => [
                'type' => 'TextBody',
                'text' => (string) ($field['text'] ?? $label),
            ],

            default => array_filter([
                'type' => 'TextInput',
                'input-type' => 'text',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
            ], fn ($v) => $v !== null),
        };
    }

    /**
     * Meta Image.src must be raw base64. Skip empty / URL-only sources to avoid 139002.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>|null
     */
    private static function mapImage(array $field): ?array
    {
        $src = (string) (
            $field['base64image']
            ?? $field['src']
            ?? $field['url']
            ?? ''
        );
        $src = trim($src);

        if ($src === '') {
            return null;
        }

        // Prefer explicit base64; reject bare http(s) URLs (invalid for Flow Image).
        if (
            ! isset($field['base64image'])
            && preg_match('#^https?://#i', $src) === 1
        ) {
            return null;
        }

        // Strip data-URI prefix if the builder stored a full data URL.
        if (str_starts_with($src, 'data:image')) {
            $comma = strpos($src, ',');
            if ($comma !== false) {
                $src = substr($src, $comma + 1);
            }
        }

        if ($src === '') {
            return null;
        }

        return [
            'type' => 'Image',
            'src' => $src,
            'width' => (int) ($field['width'] ?? 200),
            'height' => (int) ($field['height'] ?? 200),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, array{id: string, title: string}>
     */
    private static function options(array $field): array
    {
        $options = $field['options'] ?? $field['choices'] ?? [];

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->filter(fn ($opt) => is_string($opt) || is_array($opt))
            ->values()
            ->map(function ($opt, int $index) {
                if (is_array($opt)) {
                    $title = (string) ($opt['title'] ?? $opt['label'] ?? $opt['value'] ?? 'Option');

                    return [
                        'id' => (string) ($opt['id'] ?? $index.'_'.str_replace(' ', '_', $title)),
                        'title' => $title,
                    ];
                }

                return [
                    'id' => $index.'_'.str_replace(' ', '_', $opt),
                    'title' => $opt,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function fieldName(array $field, int $index): string
    {
        $name = (string) ($field['name'] ?? '');

        if ($name !== '') {
            return $name;
        }

        $label = str_replace(' ', '_', (string) ($field['label'] ?? 'field'));

        return $label.'_'.$index;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @return array<string, mixed>
     */
    private static function mapFooter(
        array $footer,
        string $screenId,
        int $index,
        \Illuminate\Support\Collection $allScreens,
    ): array {
        $label = (string) ($footer['label'] ?? $footer['text'] ?? 'Continue');
        $isLast = self::isTerminalScreen($allScreens[$index] ?? [], $index, $allScreens);

        return [
            'type' => 'Footer',
            'label' => $label,
            'on-click-action' => self::footerAction($screenId, $index, $allScreens, $isLast),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @return array<string, mixed>
     */
    private static function defaultFooter(
        string $screenId,
        int $index,
        \Illuminate\Support\Collection $allScreens,
        bool $isLast,
    ): array {
        return [
            'type' => 'Footer',
            'label' => $isLast ? 'Submit' : 'Continue',
            'on-click-action' => self::footerAction($screenId, $index, $allScreens, $isLast),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @return array<string, mixed>
     */
    private static function footerAction(
        string $screenId,
        int $index,
        \Illuminate\Support\Collection $allScreens,
        bool $isLast,
    ): array {
        if ($isLast) {
            return [
                'name' => 'complete',
                'payload' => self::payloadObject(self::buildCompletePayload($allScreens)),
            ];
        }

        $current = $allScreens->get($index) ?? [];
        $nextIds = self::resolveNextScreenIds($current, $index, $allScreens);
        $nextId = $nextIds[0] ?? 'SUCCESS';

        return [
            'name' => 'navigate',
            'next' => [
                'type' => 'screen',
                'name' => $nextId,
            ],
            'payload' => self::payloadObject(self::buildNavigatePayload($current, $screenId)),
        ];
    }

    /**
     * Ensure empty payload encodes as `{}` (Meta rejects `[]`).
     *
     * @param  array<string, string>  $payload
     * @return array<string, string>|\stdClass
     */
    private static function payloadObject(array $payload): array|\stdClass
    {
        return $payload === [] ? new \stdClass : $payload;
    }

    /**
     * Navigate: only current screen input fields (legacy EditFlow).
     *
     * @param  array<string, mixed>  $screen
     * @return array<string, string>
     */
    private static function buildNavigatePayload(array $screen, string $screenId): array
    {
        $payload = [];

        foreach (self::inputFieldsOnScreen($screen) as $fieldIndex => $field) {
            $name = self::fieldName($field, (int) $fieldIndex);
            $payload[$name] = "\${screen.{$screenId}.form.{$name}}";
        }

        return $payload;
    }

    /**
     * Complete: all input fields across every screen.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @return array<string, string>
     */
    private static function buildCompletePayload(\Illuminate\Support\Collection $allScreens): array
    {
        $payload = [];

        foreach ($allScreens as $screenIndex => $screen) {
            $screenId = self::screenId($screen, (int) $screenIndex);

            foreach (self::inputFieldsOnScreen($screen) as $fieldIndex => $field) {
                $name = self::fieldName($field, (int) $fieldIndex);
                $payload[$name] = "\${screen.{$screenId}.form.{$name}}";
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $screen
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private static function inputFieldsOnScreen(array $screen): \Illuminate\Support\Collection
    {
        return collect($screen['fields'] ?? [])
            ->filter(fn ($f) => is_array($f) && self::isInputField($f))
            ->values();
    }
}
