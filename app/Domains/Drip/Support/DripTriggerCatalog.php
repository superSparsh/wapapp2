<?php

declare(strict_types=1);

namespace App\Domains\Drip\Support;

use App\Models\DripCampaign;
use Illuminate\Validation\Rule;

final class DripTriggerCatalog
{
    /**
     * @return list<string>
     */
    public static function allTypeKeys(): array
    {
        return array_keys(config('drip-triggers.types', []));
    }

    public static function normalizeType(?string $type): string
    {
        $type = (string) $type;
        $aliases = config('drip-triggers.aliases', []);

        if (isset($aliases[$type])) {
            return (string) $aliases[$type];
        }

        if (in_array($type, self::allTypeKeys(), true)) {
            return $type;
        }

        return 'welcome-new-subscriber';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function type(string $key): ?array
    {
        $key = self::normalizeType($key);

        return config("drip-triggers.types.{$key}");
    }

    public static function label(?string $type): string
    {
        return self::type((string) $type)['label'] ?? 'Automation trigger';
    }

    public static function treeLabel(?string $type): string
    {
        return self::type((string) $type)['tree'] ?? self::label($type);
    }

    public static function intro(?string $type): string
    {
        return self::type((string) $type)['intro'] ?? '';
    }

    public static function description(?string $type): string
    {
        return self::type((string) $type)['description'] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function fieldsFor(string $type): array
    {
        return self::type($type)['fields'] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function groupedOptions(): array
    {
        $groups = config('drip-triggers.groups', []);
        $types = config('drip-triggers.types', []);
        $result = [];

        foreach ($groups as $groupLabel => $keys) {
            $options = [];
            foreach ($keys as $key) {
                if (! isset($types[$key])) {
                    continue;
                }
                $options[$key] = $types[$key]['label'];
            }
            if ($options !== []) {
                $result[$groupLabel] = $options;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function sanitizeOptions(string $type, array $options): array
    {
        $type = self::normalizeType($type);
        $allowed = self::fieldsFor($type);
        $clean = [];

        foreach ($allowed as $field) {
            if (! array_key_exists($field, $options)) {
                continue;
            }

            $value = $options[$field];

            if ($field === 'days_of_week' || $field === 'days_of_month') {
                $clean[$field] = array_values(array_map('intval', (array) $value));
                continue;
            }

            $clean[$field] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $type): array
    {
        $type = self::normalizeType($type);
        $rules = [
            'trigger_type' => ['required', 'string', Rule::in(self::allTypeKeys())],
            'trigger_options' => ['nullable', 'array'],
        ];

        foreach (self::fieldsFor($type) as $field) {
            $rules["trigger_options.{$field}"] = match ($field) {
                'date' => ['required', 'date'],
                'at' => ['required', 'string', 'max:16'],
                'before', 'delay' => ['required', 'string', Rule::in(array_keys(config('drip-triggers.delay_before_options', [])))],
                'field' => ['required', 'string', 'max:64'],
                'tag_name' => ['required', 'string', 'max:64'],
                'days_of_week' => ['required', 'array', 'min:1'],
                'days_of_week.*' => ['integer', 'between:0,6'],
                'days_of_month' => ['required', 'array', 'min:1'],
                'days_of_month.*' => ['integer', 'between:1,31'],
                'woo_source' => ['required', 'string', 'max:191'],
                default => ['nullable'],
            };
        }

        return $rules;
    }

    public static function canvasLabel(DripCampaign $campaign): string
    {
        return self::treeLabel($campaign->trigger_type);
    }
}
