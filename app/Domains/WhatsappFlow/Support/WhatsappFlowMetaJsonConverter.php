<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Support;

/**
 * Converts the internal builder JSON schema to Meta WhatsApp Flow JSON v6.3.
 */
final class WhatsappFlowMetaJsonConverter
{
    private const VERSION = '6.3';

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
            $nextId = $screen['next_screen'] ?? null;

            if (is_string($nextId) && $nextId !== '') {
                $routingModel[$screenId] = [$nextId];
            } else {
                $next = $screens->get($index + 1);
                $routingModel[$screenId] = $next !== null
                    ? [self::screenId($next, $index + 1)]
                    : [];
            }
        }

        $metaScreens = $screens->map(function (array $screen, int $index) use ($screens): array {
            $screenId = self::screenId($screen, $index);
            $isLast = $index === $screens->count() - 1;
            $fields = collect($screen['fields'] ?? [])->filter(fn ($f) => is_array($f))->values();
            $inputFields = $fields->filter(fn (array $f) => ($f['type'] ?? '') !== 'footer');
            $footer = $fields->firstWhere('type', 'footer');

            $children = $inputFields
                ->map(fn (array $field, int $fieldIndex) => self::mapField($field, $fieldIndex))
                ->filter()
                ->values()
                ->all();

            if ($footer !== null) {
                $children[] = self::mapFooter($footer, $screenId, $screen, $index, $screens, $inputFields);
            } elseif (! $isLast) {
                $nextScreen = $screens->get($index + 1);
                $children[] = self::defaultFooter($screenId, $screen, $index, $screens, $inputFields, false);
            } else {
                $children[] = self::defaultFooter($screenId, $screen, $index, $screens, $inputFields, true);
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

            'phone' => array_filter([
                'type' => 'TextInput',
                'input-type' => 'phone',
                'label' => $label,
                'name' => $name,
                'required' => $required,
                'helper-text' => $helperText,
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

            'image' => [
                'type' => 'Image',
                'src' => (string) ($field['src'] ?? $field['url'] ?? ''),
                'width' => (int) ($field['width'] ?? 200),
                'height' => (int) ($field['height'] ?? 200),
            ],

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
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $inputFields
     * @return array<string, mixed>
     */
    private static function mapFooter(
        array $footer,
        string $screenId,
        array $screen,
        int $index,
        \Illuminate\Support\Collection $allScreens,
        \Illuminate\Support\Collection $inputFields,
    ): array {
        $label = (string) ($footer['label'] ?? $footer['text'] ?? 'Continue');
        $isLast = $index === $allScreens->count() - 1;

        return [
            'type' => 'Footer',
            'label' => $label,
            'on-click-action' => self::footerAction($screenId, $index, $allScreens, $inputFields, $isLast),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $inputFields
     * @return array<string, mixed>
     */
    private static function defaultFooter(
        string $screenId,
        array $screen,
        int $index,
        \Illuminate\Support\Collection $allScreens,
        \Illuminate\Support\Collection $inputFields,
        bool $isLast,
    ): array {
        return [
            'type' => 'Footer',
            'label' => $isLast ? 'Submit' : 'Continue',
            'on-click-action' => self::footerAction($screenId, $index, $allScreens, $inputFields, $isLast),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $inputFields
     * @return array<string, mixed>
     */
    private static function footerAction(
        string $screenId,
        int $index,
        \Illuminate\Support\Collection $allScreens,
        \Illuminate\Support\Collection $inputFields,
        bool $isLast,
    ): array {
        $payload = self::buildPayload($allScreens, $inputFields);

        if ($isLast) {
            return [
                'name' => 'complete',
                'payload' => $payload,
            ];
        }

        $nextScreen = $allScreens->get($index + 1);
        $nextId = $nextScreen !== null ? self::screenId($nextScreen, $index + 1) : 'SUCCESS';

        return [
            'name' => 'navigate',
            'next' => [
                'type' => 'screen',
                'name' => $nextId,
            ],
            'payload' => $payload,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $allScreens
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $inputFields
     * @return array<string, string>
     */
    private static function buildPayload(
        \Illuminate\Support\Collection $allScreens,
        \Illuminate\Support\Collection $inputFields,
    ): array {
        $payload = [];

        foreach ($allScreens as $screenIndex => $screen) {
            $screenId = self::screenId($screen, (int) $screenIndex);
            $fields = collect($screen['fields'] ?? [])
                ->filter(fn ($f) => is_array($f) && ($f['type'] ?? '') !== 'footer');

            foreach ($fields as $fieldIndex => $field) {
                $name = self::fieldName($field, (int) $fieldIndex);
                $payload[$name] = "\${screen.{$screenId}.form.{$name}}";
            }
        }

        return $payload;
    }
}
