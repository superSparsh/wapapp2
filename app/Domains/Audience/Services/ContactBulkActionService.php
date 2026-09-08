<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\ContactTag;
use App\Models\Contact;
use InvalidArgumentException;

class ContactBulkActionService
{
    /**
     * @param  list<int>  $contactIds
     */
    public function move(array $contactIds, int $mailListId): int
    {
        return Contact::query()
            ->whereIn('id', $contactIds)
            ->update(['mail_list_id' => $mailListId]);
    }

    /**
     * @param  list<int>  $contactIds
     */
    public function copy(array $contactIds, int $mailListId): int
    {
        $copied = 0;

        Contact::query()
            ->whereIn('id', $contactIds)
            ->each(function (Contact $contact) use ($mailListId, &$copied): void {
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

                $copied++;
            });

        return $copied;
    }

    /**
     * @param  list<int>  $contactIds
     * @param  list<string>  $tags
     */
    public function bulkAssignTags(array $contactIds, array $tags): int
    {
        $assigned = 0;

        $contacts = Contact::query()->whereIn('id', $contactIds)->get();

        foreach ($contacts as $contact) {
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

            $assigned++;
        }

        return $assigned;
    }

    public function apply(string $operation, array $contactIds, array $options = []): int
    {
        return match ($operation) {
            'move' => $this->move($contactIds, (int) ($options['mail_list_id'] ?? 0)),
            'copy' => $this->copy($contactIds, (int) ($options['mail_list_id'] ?? 0)),
            'bulkAssignTags' => $this->bulkAssignTags($contactIds, (array) ($options['tags'] ?? [])),
            default => throw new InvalidArgumentException("Unsupported bulk action [{$operation}]."),
        };
    }
}
