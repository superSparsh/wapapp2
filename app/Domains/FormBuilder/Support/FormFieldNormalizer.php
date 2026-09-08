<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Support;

use App\Domains\FormBuilder\Enums\FieldType;

/**
 * Maps legacy Form Builder field shapes (PascalCase types, phone_number name, etc.)
 * onto the current FieldType enum values.
 */
final class FormFieldNormalizer
{
    /**
     * @param  array<int, mixed>  $fields
     * @return list<array<string, mixed>>
     */
    public static function normalizeList(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $normalized[] = self::normalizeField($field);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public static function normalizeField(array $field): array
    {
        $name = strtolower(trim((string) ($field['name'] ?? $field['id'] ?? '')));
        $rawType = (string) ($field['type'] ?? 'input');
        $type = self::resolveType($rawType, $name);

        $label = (string) ($field['label'] ?? '');
        if ($label === '') {
            $label = $type->label();
        }

        $placeholder = (string) ($field['placeholder'] ?? '');
        if ($placeholder === '' && ! in_array($type, [FieldType::Logo, FieldType::Header, FieldType::Paragraph, FieldType::Checkbox], true)) {
            $placeholder = $label;
        }

        $text = (string) ($field['text'] ?? '');
        if ($text === '' && in_array($type, [FieldType::Header, FieldType::Paragraph], true)) {
            $text = $placeholder !== '' ? $placeholder : $label;
        }

        $requiredMessage = (string) ($field['required_message'] ?? $field['requiredMessage'] ?? '');

        $imagePath = $field['image_path'] ?? $field['src'] ?? null;
        if (is_string($imagePath) && $imagePath === '') {
            $imagePath = null;
        }

        $options = self::normalizeOptions($field['options'] ?? null);

        $entry = [
            'type' => $type->value,
            'label' => $label,
            'required' => (bool) ($field['required'] ?? $type->isRequiredByDefault()),
            'placeholder' => $placeholder,
            'text' => $text,
            'required_message' => $requiredMessage,
        ];

        if ($type === FieldType::Dropdown) {
            $entry['options'] = $options !== [] ? $options : ['Option 1', 'Option 2'];
        }

        if ($type === FieldType::Logo) {
            $entry['image_path'] = $imagePath;
        }

        if ($type->isLocked() || ! empty($field['locked']) || ! empty($field['unremovable'])) {
            $entry['locked'] = true;
        }

        // Preserve useful legacy metadata without breaking validation.
        if (isset($field['id']) && filled($field['id'])) {
            $entry['id'] = $field['id'];
        }
        if ($name !== '') {
            $entry['name'] = $name;
        }

        return $entry;
    }

    private static function resolveType(string $rawType, string $name): FieldType
    {
        $candidate = strtolower(trim($rawType));

        // Legacy dropdown type was "Select".
        $candidate = match ($candidate) {
            'select' => FieldType::Dropdown->value,
            'whatsapp_number', 'whatsapp', 'phone_number', 'tel' => FieldType::Phone->value,
            default => $candidate,
        };

        // Legacy defaults were type=Input with a semantic name/id.
        if (in_array($name, ['phone', 'phone_number', 'whatsapp_number', 'whatsapp_phone_number'], true)) {
            return FieldType::Phone;
        }
        if (in_array($name, ['first_name', 'firstname'], true)) {
            return FieldType::FirstName;
        }
        if (in_array($name, ['last_name', 'lastname'], true)) {
            return FieldType::LastName;
        }

        return FieldType::tryFrom($candidate) ?? FieldType::Input;
    }

    /**
     * @return list<string>
     */
    private static function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (is_string($option) || is_numeric($option)) {
                $value = trim((string) $option);
                if ($value !== '') {
                    $normalized[] = $value;
                }

                continue;
            }

            if (is_array($option)) {
                $value = trim((string) ($option['label'] ?? $option['value'] ?? $option['text'] ?? ''));
                if ($value !== '') {
                    $normalized[] = $value;
                }
            }
        }

        return array_values($normalized);
    }
}
