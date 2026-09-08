<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\ContactTag;
use App\Models\Contact;
use InvalidArgumentException;

class DripContactOperationService
{
    public function apply(string $operation, Contact $contact, array $options = []): void
    {
        match ($operation) {
            'tag' => $this->tag($contact, $options),
            'move' => $this->move($contact, $options),
            'copy' => $this->copy($contact, $options),
            'update' => $this->update($contact, $options),
            default => throw new InvalidArgumentException("Unsupported drip contact operation [{$operation}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function tag(Contact $contact, array $options): void
    {
        $tags = (array) ($options['tags'] ?? $options['tag'] ?? []);

        if (isset($options['tag']) && is_string($options['tag'])) {
            $tags = [$options['tag']];
        }

        foreach ($tags as $tag) {
            $name = trim((string) $tag);

            if ($name === '') {
                continue;
            }

            ContactTag::query()->firstOrCreate([
                'contact_id' => $contact->id,
                'name' => $name,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function move(Contact $contact, array $options): void
    {
        $mailListId = (int) ($options['mail_list_id'] ?? $options['target_list_id'] ?? 0);

        abort_if($mailListId < 1, 422, 'mail_list_id is required for move operation.');

        $contact->forceFill(['mail_list_id' => $mailListId])->save();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function copy(Contact $contact, array $options): void
    {
        $mailListId = (int) ($options['mail_list_id'] ?? $options['target_list_id'] ?? 0);

        abort_if($mailListId < 1, 422, 'mail_list_id is required for copy operation.');

        Contact::query()->create([
            'phone' => $contact->phone,
            'name' => $contact->name,
            'email' => $contact->email,
            'country_code' => $contact->country_code,
            'opt_in_status' => $contact->opt_in_status,
            'opted_in_at' => $contact->opted_in_at,
            'source' => $contact->source,
            'mail_list_id' => $mailListId,
            'custom_fields' => $contact->custom_fields,
            'metadata' => $contact->metadata,
            'status' => ContactStatus::Subscribed,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function update(Contact $contact, array $options): void
    {
        $fieldName = (string) ($options['field_name'] ?? '');
        $fieldValue = $options['field_value'] ?? null;

        if ($fieldName === '') {
            return;
        }

        if (in_array($fieldName, ['name', 'email', 'phone', 'country_code', 'notes'], true)) {
            $contact->forceFill([$fieldName => $fieldValue])->save();
            return;
        }

        $customFields = (array) ($contact->custom_fields ?? []);
        $customFields[$fieldName] = $fieldValue;
        $contact->forceFill(['custom_fields' => $customFields])->save();
    }
}
