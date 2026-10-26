<?php

declare(strict_types=1);

namespace App\Domains\Audience\Support;

use App\Models\Contact;

final class UnsubscribeToken
{
    public static function forContact(?Contact $contact): string
    {
        if (! $contact instanceof Contact) {
            return 'unsub';
        }

        $tenantId = trim((string) (tenant('id') ?? ''));
        $uuid = trim((string) $contact->uuid);

        if ($tenantId === '' || $uuid === '') {
            return (string) $contact->id;
        }

        return $tenantId.'.'.$uuid;
    }

    /**
     * @return array{tenant_id: string, contact_uuid: string}|null
     */
    public static function parse(string $token): ?array
    {
        $token = trim(rawurldecode($token));

        if (preg_match('/^(.+)\.([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$/', $token, $matches) !== 1) {
            return null;
        }

        $tenantId = trim($matches[1]);
        if ($tenantId === '') {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'contact_uuid' => strtolower($matches[2]),
        ];
    }
}
