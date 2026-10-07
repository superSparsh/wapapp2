<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Models\ListField;
use App\Domains\Audience\Models\ListFieldOption;
use App\Models\Contact;
use App\Models\MailList;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListFieldService
{
    /**
     * Default system fields seeded for every mail list (legacy parity).
     *
     * @return list<array{tag: string, label: string, type: string, required: bool, visible: bool, sort_order: int}>
     */
    public function defaultFieldDefinitions(): array
    {
        return [
            [
                'tag' => 'country_code',
                'label' => 'Country Code',
                'type' => ListField::TYPE_TEXT,
                'required' => true,
                'visible' => true,
                'sort_order' => 0,
            ],
            [
                'tag' => 'phone_number',
                'label' => 'WhatsApp Number',
                'type' => ListField::TYPE_TEXT,
                'required' => true,
                'visible' => true,
                'sort_order' => 1,
            ],
            [
                'tag' => 'FIRST_NAME',
                'label' => 'First name',
                'type' => ListField::TYPE_TEXT,
                'required' => false,
                'visible' => true,
                'sort_order' => 2,
            ],
            [
                'tag' => 'LAST_NAME',
                'label' => 'Last name',
                'type' => ListField::TYPE_TEXT,
                'required' => false,
                'visible' => true,
                'sort_order' => 3,
            ],
        ];
    }

    /**
     * Ensure protected default fields exist for a list (idempotent).
     */
    public function ensureDefaultFields(MailList $mailList): void
    {
        foreach ($this->defaultFieldDefinitions() as $definition) {
            $existing = ListField::query()
                ->where('mail_list_id', $mailList->id)
                ->where('tag', $definition['tag'])
                ->first();

            if ($existing !== null) {
                continue;
            }

            ListField::query()->create([
                'mail_list_id' => $mailList->id,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'tag' => $definition['tag'],
                'default_value' => null,
                'required' => $definition['required'],
                'visible' => $definition['visible'],
                'sort_order' => $definition['sort_order'],
            ]);
        }
    }

    /**
     * Visible custom fields shown as extra columns on the subscribers listing.
     * Core phone/name columns already cover the protected system tags.
     *
     * @return Collection<int, ListField>
     */
    public function visibleListingFields(MailList $mailList): Collection
    {
        return ListField::query()
            ->where('mail_list_id', $mailList->id)
            ->where('visible', true)
            ->whereNotNull('tag')
            ->where('tag', '!=', '')
            ->whereNotIn('tag', ListField::PROTECTED_TAGS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'uuid', 'label', 'tag', 'type']);
    }

    /**
     * Custom fields shown on Add/Edit subscriber forms (non-system tags only).
     *
     * @return Collection<int, ListField>
     */
    public function editableFormFields(MailList $mailList): Collection
    {
        return ListField::query()
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->where('mail_list_id', $mailList->id)
            ->whereNotNull('tag')
            ->where('tag', '!=', '')
            ->whereNotIn('tag', ListField::PROTECTED_TAGS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Keep only known list-field tags and normalize values for storage.
     *
     * @param  array<string, mixed>|null  $input
     * @return array<string, mixed>
     */
    public function sanitizeCustomFields(MailList $mailList, ?array $input): array
    {
        if ($input === null || $input === []) {
            return [];
        }

        $fields = $this->editableFormFields($mailList)->keyBy('tag');
        $clean = [];

        foreach ($input as $tag => $value) {
            $tag = trim((string) $tag);
            if ($tag === '' || ! $fields->has($tag)) {
                continue;
            }

            /** @var ListField $field */
            $field = $fields->get($tag);

            if (is_array($value)) {
                $parts = array_values(array_filter(array_map(
                    static fn ($item): string => trim(is_scalar($item) ? (string) $item : ''),
                    $value,
                ), static fn (string $item): bool => $item !== ''));

                if ($parts === []) {
                    continue;
                }

                $clean[$tag] = in_array($field->type, [ListField::TYPE_MULTISELECT, ListField::TYPE_CHECKBOX], true)
                    ? $parts
                    : implode(', ', $parts);
                continue;
            }

            if (is_bool($value)) {
                $clean[$tag] = $value ? '1' : '0';
                continue;
            }

            $string = trim((string) $value);
            if ($string === '') {
                continue;
            }

            $clean[$tag] = $string;
        }

        return $clean;
    }

    public function generateUniqueTag(int $mailListId, string $label, ?int $ignoreFieldId = null): string
    {
        $base = Str::upper(Str::slug(trim($label) !== '' ? trim($label) : 'FIELD', '_'));
        if ($base === '') {
            $base = 'FIELD';
        }

        // Protected tags are reserved for system fields.
        if (in_array($base, ListField::PROTECTED_TAGS, true)) {
            $base = $base.'_CUSTOM';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->tagExists($mailListId, $candidate, $ignoreFieldId)) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Parse option lines from a textarea (one per line; optional label|value).
     *
     * @return list<array{label: string, value: string}>
     */
    public function parseOptionsText(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $options = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$label, $value] = array_map('trim', explode('|', $line, 2));
            } else {
                $label = $line;
                $value = $line;
            }

            if ($label === '' && $value === '') {
                continue;
            }

            $options[] = [
                'label' => $label !== '' ? $label : $value,
                'value' => $value !== '' ? $value : $label,
            ];
        }

        return $options;
    }

    /**
     * Replace all options for a field (legacy-style edit).
     *
     * @param  list<array{label: string, value: string}>  $options
     */
    public function syncOptions(ListField $field, array $options): void
    {
        if (! $field->hasOptions()) {
            $field->options()->delete();

            return;
        }

        $field->options()->delete();

        foreach (array_values($options) as $index => $option) {
            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? $label));
            if ($label === '' && $value === '') {
                continue;
            }

            ListFieldOption::query()->create([
                'list_field_id' => $field->id,
                'label' => $label !== '' ? $label : $value,
                'value' => $value !== '' ? $value : $label,
                'sort_order' => $index,
            ]);
        }
    }

    public function formatContactValue(Contact $contact, ListField $field): string
    {
        $tag = (string) $field->tag;
        if ($tag === '') {
            return '—';
        }

        $raw = data_get($contact->custom_fields, $tag);
        if ($raw === null || $raw === '') {
            return '—';
        }

        if (is_array($raw)) {
            $parts = array_values(array_filter(array_map(
                static fn ($item): string => trim(is_scalar($item) ? (string) $item : ''),
                $raw,
            ), static fn (string $item): bool => $item !== ''));

            return $parts === [] ? '—' : implode(', ', $parts);
        }

        if (is_bool($raw)) {
            return $raw ? 'Yes' : 'No';
        }

        return trim((string) $raw) !== '' ? trim((string) $raw) : '—';
    }

    private function tagExists(int $mailListId, string $tag, ?int $ignoreFieldId): bool
    {
        return ListField::query()
            ->where('mail_list_id', $mailListId)
            ->where('tag', $tag)
            ->when($ignoreFieldId !== null, fn ($q) => $q->where('id', '!=', $ignoreFieldId))
            ->exists();
    }
}
