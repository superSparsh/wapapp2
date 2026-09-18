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
            if (is_string($payload)) {
                $payload = $this->decodeJsonString($payload);
            }
        }

        if (! is_array($payload)) {
            return [];
        }

        $payload = $this->unwrapEnvelope($payload);

        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            $items = [];
            foreach ($payload as $item) {
                if (is_array($item)) {
                    $items[] = $this->normalizeItem($item);
                }
            }

            return array_values($items);
        }

        return [$this->normalizeItem($payload)];
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
        $messageId = $item['MessageId'] ?? $item['messageId'] ?? null;

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    public function statusIdempotencyKey(array $item): ?string
    {
        $messageId = $this->messageIdempotencyKey($item);
        $status = $item['Status'] ?? $item['status'] ?? null;

        if ($messageId === null || ! is_string($status) || $status === '') {
            return null;
        }

        return $messageId.':'.$status;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    private function unwrapEnvelope(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'Data', 'items', 'Items', 'messages', 'Messages', 'payload', 'Payload', 'result', 'Result'] as $key) {
            $inner = $this->decodeIfJsonString($payload[$key] ?? null);
            if (! is_array($inner) || $inner === []) {
                continue;
            }

            if (array_is_list($inner) && isset($inner[0]) && is_array($inner[0])) {
                return $inner;
            }

            if ($this->looksLikeMessageItem($inner)) {
                return $inner;
            }

            $deeper = $this->unwrapEnvelope($inner);
            if ($deeper !== $inner) {
                return $deeper;
            }
        }

        $value = data_get($payload, 'entry.0.changes.0.value');
        if (is_array($value)) {
            $cloudItems = $this->cloudApiMessages($value);
            if ($cloudItems !== []) {
                return $cloudItems;
            }
        }

        foreach ($payload as $value) {
            $inner = $this->decodeIfJsonString($value);
            if (! is_array($inner) || $inner === []) {
                continue;
            }

            if (array_is_list($inner) && isset($inner[0]) && is_array($inner[0]) && $this->looksLikeMessageItem($inner[0])) {
                return $inner;
            }
        }

        return $payload;
    }

    private function decodeIfJsonString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $value;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<array<string, mixed>>
     */
    private function cloudApiMessages(array $value): array
    {
        $messages = $value['messages'] ?? $value['Messages'] ?? null;
        if (! is_array($messages) || $messages === []) {
            return [];
        }

        $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
        $to = (string) ($metadata['display_phone_number'] ?? $metadata['phone_number'] ?? '');
        $contacts = $value['contacts'] ?? [];
        $name = '';
        if (is_array($contacts[0] ?? null)) {
            $name = (string) data_get($contacts[0], 'profile.name', '');
        }

        $items = [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $message['_display_phone_number'] = $to;
            $message['_contact_name'] = $name;
            $items[] = $message;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function looksLikeMessageItem(array $item): bool
    {
        $hasFrom = isset($item['From']) || isset($item['from']);
        $hasTo = isset($item['To']) || isset($item['to']) || isset($item['_display_phone_number']);
        $hasId = isset($item['MessageId']) || isset($item['messageId']) || isset($item['id']);

        return $hasFrom && ($hasTo || $hasId);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $from = $item['From'] ?? $item['from'] ?? $item['msg_from'] ?? '';
        $to = $item['To']
            ?? $item['to']
            ?? $item['msg_to']
            ?? $item['_display_phone_number']
            ?? data_get($item, 'metadata.display_phone_number')
            ?? '';
        $messageId = $item['MessageId']
            ?? $item['messageId']
            ?? $item['MsgId']
            ?? $item['msgId']
            ?? $item['id']
            ?? '';
        $name = $item['Name']
            ?? $item['name']
            ?? $item['_contact_name']
            ?? data_get($item, 'profile.name')
            ?? '';
        $type = $item['Type'] ?? $item['type'] ?? 'TEXT';
        $status = $item['Status'] ?? $item['status'] ?? null;
        $message = $item['Message'] ?? $item['message'] ?? $item['text'] ?? $item['body'] ?? null;

        $item['From'] = is_scalar($from) ? (string) $from : '';
        $item['To'] = is_scalar($to) ? (string) $to : '';
        $item['MessageId'] = is_scalar($messageId) ? (string) $messageId : '';
        $item['Name'] = is_scalar($name) ? (string) $name : '';
        $item['Type'] = is_scalar($type) ? (string) $type : 'TEXT';

        if (is_scalar($status) && (string) $status !== '') {
            $item['Status'] = (string) $status;
        }

        if ($message !== null) {
            $item['Message'] = $message;
        }

        return $item;
    }

    private function decodeJsonString(string $payload): mixed
    {
        $clean = str_replace(['"[', ']"'], ['[', ']'], $payload);

        return json_decode($clean, true);
    }
}
