<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Support;

/**
 * Build a UI-friendly interactive preview for inbox bubbles (outbound + inbound).
 */
final class InboxInteractivePreview
{
    /**
     * @param  array<string, mixed>|null  $interactive
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     body: string,
     *     buttons: list<string>,
     *     type: ?string,
     *     flow_reply: ?array{
     *         title: string,
     *         flow_name: ?string,
     *         fields: list<array{name: string, label: string, value: string}>
     *     }
     * }|null
     */
    public static function build(?array $interactive, string $fallbackBody, array $metadata = []): ?array
    {
        $nfm = self::resolveNfmReply($interactive, $metadata);
        $flowReply = $nfm !== null ? self::flowReplyPreview($nfm) : null;

        if ($interactive === null && $flowReply === null) {
            return null;
        }

        $bodyRaw = is_array($interactive) ? ($interactive['body'] ?? null) : null;
        if (is_array($bodyRaw)) {
            $body = trim((string) ($bodyRaw['text'] ?? $fallbackBody));
        } elseif (is_string($bodyRaw)) {
            $body = trim($bodyRaw);
        } else {
            $body = trim($fallbackBody);
        }

        // Inbound Flow submit often has body "Sent" — prefer a clear title when we have answers.
        if ($flowReply !== null) {
            $body = $flowReply['title'];
        }

        $buttons = [];
        $interactiveSafe = is_array($interactive) ? $interactive : [];

        foreach ($interactiveSafe['action']['buttons'] ?? [] as $button) {
            if (! is_array($button)) {
                continue;
            }

            $title = (string) (
                $button['reply']['title']
                ?? $button['title']
                ?? $button['text']
                ?? $button['label']
                ?? ''
            );

            if ($title !== '') {
                $buttons[] = $title;
            }
        }

        foreach ($interactiveSafe['action']['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach ($section['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $title = trim((string) ($row['title'] ?? ''));
                if ($title !== '') {
                    $buttons[] = $title;
                }
            }
        }

        // Inbound button / list replies
        foreach (['button_reply', 'list_reply'] as $replyKey) {
            $reply = is_array($interactiveSafe[$replyKey] ?? null) ? $interactiveSafe[$replyKey] : null;
            if ($reply === null) {
                continue;
            }
            $title = trim((string) ($reply['title'] ?? $reply['id'] ?? ''));
            if ($title !== '') {
                $buttons[] = $title;
                if ($body === '' || strcasecmp($body, $fallbackBody) === 0) {
                    $body = $title;
                }
            }
        }

        $type = null;
        if ($flowReply !== null) {
            $type = 'nfm_reply';
        } elseif (isset($interactiveSafe['type'])) {
            $type = (string) $interactiveSafe['type'];
        } elseif (($metadata['interactive_reply_type'] ?? '') !== '') {
            $type = (string) $metadata['interactive_reply_type'];
        }

        return [
            'body' => $body !== '' ? $body : $fallbackBody,
            'buttons' => array_values(array_unique($buttons)),
            'type' => $type,
            'flow_reply' => $flowReply,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $interactive
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private static function resolveNfmReply(?array $interactive, array $metadata): ?array
    {
        $candidates = [];

        if (is_array($interactive)) {
            $candidates[] = $interactive['nfm_reply'] ?? null;
            if (strtolower((string) ($interactive['type'] ?? '')) === 'nfm_reply') {
                $candidates[] = $interactive;
            }
        }

        $candidates[] = $metadata['nfm_reply'] ?? null;

        $raw = is_array($metadata['raw_message'] ?? null) ? $metadata['raw_message'] : [];
        $candidates[] = $raw['nfm_reply'] ?? null;
        $rawInteractive = is_array($raw['interactive'] ?? null) ? $raw['interactive'] : [];
        $candidates[] = $rawInteractive['nfm_reply'] ?? null;
        if (strtolower((string) ($rawInteractive['type'] ?? '')) === 'nfm_reply') {
            $candidates[] = $rawInteractive;
        }
        if (strtolower((string) ($raw['type'] ?? '')) === 'nfm_reply') {
            $candidates[] = $raw;
        }

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (
                isset($candidate['response_json'])
                || isset($candidate['responseJson'])
                || isset($candidate['body'])
                || isset($candidate['name'])
                || isset($candidate['nfm_reply'])
            ) {
                if (is_array($candidate['nfm_reply'] ?? null)) {
                    return $candidate['nfm_reply'];
                }

                return $candidate;
            }
        }

        if (($metadata['interactive_reply_type'] ?? '') === 'nfm_reply' && is_array($interactive)) {
            return $interactive;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $nfm
     * @return array{title: string, flow_name: ?string, fields: list<array{name: string, label: string, value: string}>}|null
     */
    private static function flowReplyPreview(array $nfm): ?array
    {
        $flowName = trim((string) ($nfm['name'] ?? ''));
        $fields = self::extractFields($nfm);

        if ($fields === [] && $flowName === '' && trim((string) ($nfm['body'] ?? '')) === '') {
            return null;
        }

        return [
            'title' => 'Form submitted',
            'flow_name' => $flowName !== '' ? $flowName : null,
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $nfm
     * @return list<array{name: string, label: string, value: string}>
     */
    private static function extractFields(array $nfm): array
    {
        $responseJson = $nfm['response_json'] ?? $nfm['responseJson'] ?? null;
        $decoded = null;

        if (is_array($responseJson)) {
            $decoded = $responseJson;
        } elseif (is_string($responseJson) && trim($responseJson) !== '') {
            $parsed = json_decode($responseJson, true);
            $decoded = is_array($parsed) ? $parsed : null;
        }

        if (! is_array($decoded)) {
            return [];
        }

        return self::flattenFields($decoded);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name: string, label: string, value: string}>
     */
    private static function flattenFields(array $data, string $prefix = ''): array
    {
        $skip = [
            'flow_token',
            'version',
            'extension_message_response',
            'screen',
            'action',
        ];

        $rows = [];

        foreach ($data as $key => $value) {
            $rawKey = is_string($key) || is_int($key) ? (string) $key : '';
            if ($rawKey === '' || in_array($rawKey, $skip, true)) {
                continue;
            }

            $safeKey = preg_replace('/[^\w.]+/', '_', $rawKey) ?? '';
            $safeKey = trim($safeKey, '._');
            if ($safeKey === '') {
                continue;
            }

            $fullKey = $prefix !== '' ? $prefix.'.'.$safeKey : $safeKey;
            $label = self::humanizeKey($fullKey);

            if (is_array($value)) {
                $isList = array_is_list($value);
                $allScalar = $isList && $value === array_filter(
                    $value,
                    static fn ($item): bool => is_scalar($item) || $item === null
                );

                if ($allScalar) {
                    $joined = implode(', ', array_map(
                        static fn ($item): string => trim((string) $item),
                        $value
                    ));
                    if ($joined !== '') {
                        $rows[] = [
                            'name' => $fullKey,
                            'label' => $label,
                            'value' => $joined,
                        ];
                    }
                } else {
                    $rows = array_merge($rows, self::flattenFields($value, $fullKey));
                }

                continue;
            }

            if (is_bool($value)) {
                $rows[] = [
                    'name' => $fullKey,
                    'label' => $label,
                    'value' => $value ? 'Yes' : 'No',
                ];

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $stringValue = trim((string) $value);
                if ($stringValue === '') {
                    continue;
                }

                $rows[] = [
                    'name' => $fullKey,
                    'label' => $label,
                    'value' => $stringValue,
                ];
            }
        }

        return $rows;
    }

    private static function humanizeKey(string $key): string
    {
        $leaf = str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key;
        $leaf = preg_replace('/_\d+$/', '', $leaf) ?? $leaf;
        $leaf = str_replace(['_', '-'], ' ', $leaf);

        return ucwords(trim($leaf));
    }
}
