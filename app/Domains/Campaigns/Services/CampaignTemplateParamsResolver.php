<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Audience\Models\ListField;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;

/**
 * Merge contact defaults, campaign defaults, and per-recipient values for CAMS TemplateParams.
 */
class CampaignTemplateParamsResolver
{
    public const CONTACT_NAME_VARS = ['full_name', 'first_name', 'last_name', 'name'];

    public const HIDDEN_GRID_VARS = ['unsub'];

    public const META_KEYS = ['template_code', 'legacy_inbox_id', 'cams_group_id', 'line_phone', 'cust_space_id', 'language'];

    /**
     * @return array<string, string>
     */
    public function forRecipient(Campaign $campaign, CampaignRecipient $recipient, ?array $listFieldIndex = null): array
    {
        $recipient->loadMissing('contact');
        $contact = $recipient->contact;
        $index = $listFieldIndex ?? $this->listFieldIndex($campaign->audience_id);

        $defaults = $this->contactDefaults($contact, (string) $recipient->contact_phone, $index);
        $campaignVars = $this->scalarStringMap((array) ($campaign->template_variables ?? []));
        $recipientVars = $this->scalarStringMap((array) ($recipient->variable_values ?? []));

        $merged = array_merge($defaults, $campaignVars, $recipientVars);

        return $this->withoutMetaKeys($merged);
    }

    /**
     * Auto-fill values for grid display (before saved recipient overrides).
     *
     * @param  list<string>  $variableNames
     * @param  array<string, string>  $listFieldIndex  variableOrLabel => custom_fields key
     * @return array{values: array<string, string>, sources: array<string, string>}
     */
    public function autoFill(
        ?Contact $contact,
        string $phone,
        array $variableNames,
        array $listFieldIndex = [],
    ): array {
        $defaults = $this->contactDefaults($contact, $phone, $listFieldIndex);
        $values = [];
        $sources = [];

        foreach ($variableNames as $name) {
            if ($name === '' || in_array($name, self::HIDDEN_GRID_VARS, true)) {
                continue;
            }

            if (array_key_exists($name, $defaults) && $defaults[$name] !== '') {
                $values[$name] = $defaults[$name];
                $sources[$name] = in_array($name, self::CONTACT_NAME_VARS, true) || $name === 'phone'
                    ? 'contact'
                    : 'list';
            }
        }

        return ['values' => $values, 'sources' => $sources];
    }

    /**
     * Map list field tag/label → custom_fields storage key (tag).
     *
     * @return array<string, string>
     */
    public function listFieldIndex(?int $mailListId): array
    {
        if ($mailListId === null || $mailListId <= 0) {
            return [];
        }

        $index = [];
        ListField::query()
            ->where('mail_list_id', $mailListId)
            ->get(['tag', 'label'])
            ->each(function (ListField $field) use (&$index): void {
                $tag = trim((string) $field->tag);
                if ($tag === '') {
                    return;
                }

                $index[$tag] = $tag;
                $label = trim((string) $field->label);
                if ($label !== '') {
                    $index[$label] = $tag;
                }
            });

        return $index;
    }

    public function isContactNameVar(string $name): bool
    {
        return in_array($name, self::CONTACT_NAME_VARS, true);
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    public function withoutMetaKeys(array $map): array
    {
        $params = [];
        foreach ($map as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }
            $name = (string) $key;
            if ($name === '' || in_array($name, self::META_KEYS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $params[$name] = (string) ($value ?? '');
            }
        }

        return $params;
    }

    /**
     * @param  array<string, string>  $listFieldIndex
     * @return array<string, string>
     */
    private function contactDefaults(?Contact $contact, string $phone, array $listFieldIndex): array
    {
        $fullName = trim((string) ($contact?->name ?? ''));
        $parts = preg_split('/\s+/', $fullName, 2) ?: [];
        $firstName = (string) ($parts[0] ?? '');
        $lastName = (string) ($parts[1] ?? '');

        $defaults = array_filter([
            'full_name' => $fullName !== '' ? $fullName : null,
            'name' => $fullName !== '' ? $fullName : null,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'phone' => $phone !== '' ? $phone : null,
            'unsub' => (string) ($contact?->id ?? 'unsub'),
        ], static fn ($value) => $value !== null && $value !== '');

        $customFields = is_array($contact?->custom_fields) ? $contact->custom_fields : [];
        foreach ($customFields as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $stringValue = trim((string) ($value ?? ''));
            if ($stringValue === '') {
                continue;
            }
            if (! array_key_exists($key, $defaults)) {
                $defaults[$key] = $stringValue;
            }
        }

        foreach ($listFieldIndex as $alias => $storageKey) {
            if (array_key_exists($alias, $defaults)) {
                continue;
            }
            $raw = $customFields[$storageKey] ?? $customFields[$alias] ?? null;
            if (! is_scalar($raw) && $raw !== null) {
                continue;
            }
            $stringValue = trim((string) ($raw ?? ''));
            if ($stringValue !== '') {
                $defaults[$alias] = $stringValue;
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    private function scalarStringMap(array $map): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $out[$key] = (string) ($value ?? '');
        }

        return $out;
    }
}
