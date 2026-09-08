<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Parsers;

class AlibabaWebhookParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parsePayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $payload = $this->decodeJsonString($payload);
        }

        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [$payload];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    public function firstItem(array $items): ?array
    {
        return $items[0] ?? null;
    }

    public function messageIdempotencyKey(array $item): ?string
    {
        $messageId = $item['MessageId'] ?? null;

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    public function statusIdempotencyKey(array $item): ?string
    {
        $messageId = $this->messageIdempotencyKey($item);
        $status = $item['Status'] ?? null;

        if ($messageId === null || ! is_string($status) || $status === '') {
            return null;
        }

        return $messageId.':'.$status;
    }

    private function decodeJsonString(string $payload): mixed
    {
        $clean = str_replace(['"[', ']"'], ['[', ']'], $payload);

        return json_decode($clean, true);
    }
}
