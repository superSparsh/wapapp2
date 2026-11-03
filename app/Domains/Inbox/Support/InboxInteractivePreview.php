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
     *     header: ?array{type: string, text: string, media_url: ?string},
     *     footer: string,
     *     actions: list<array{text: string, type: string}>,
     *     list: ?array{
     *         button: string,
     *         sections: list<array{title: string, rows: list<array{title: string, description: string}>}>
     *     },
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

        $interactiveSafe = is_array($interactive) ? $interactive : [];

        $bodyRaw = $interactiveSafe['body'] ?? null;
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

        $actions = [];
        $buttons = [];

        foreach ($interactiveSafe['action']['buttons'] ?? [] as $button) {
            if (! is_array($button)) {
                continue;
            }

            $title = trim((string) (
                $button['reply']['title']
                ?? $button['title']
                ?? $button['text']
                ?? $button['label']
                ?? ''
            ));

            if ($title === '') {
                continue;
            }

            $buttons[] = $title;
            $actions[] = [
                'text' => $title,
                'type' => self::normalizeActionType((string) ($button['type'] ?? 'quick_reply')),
            ];
        }

        $list = self::buildListPreview($interactiveSafe);
        if ($list !== null) {
            $actions[] = [
                'text' => $list['button'],
                'type' => 'list',
            ];
            foreach ($list['sections'] as $section) {
                foreach ($section['rows'] as $row) {
                    if ($row['title'] !== '') {
                        $buttons[] = $row['title'];
                    }
                }
            }
        }

        $cta = self::ctaAction($interactiveSafe);
        if ($cta !== null) {
            $actions[] = $cta;
            $buttons[] = $cta['text'];
        }

        $flowCta = self::flowCtaAction($interactiveSafe);
        if ($flowCta !== null) {
            $actions[] = $flowCta;
            $buttons[] = $flowCta['text'];
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
        } elseif (($metadata['interactive_type'] ?? '') !== '') {
            $type = (string) $metadata['interactive_type'];
        }

        return [
            'body' => $body !== '' ? $body : $fallbackBody,
            'buttons' => array_values(array_unique($buttons)),
            'type' => $type,
            'header' => self::buildHeader($interactiveSafe),
            'footer' => self::footerText($interactiveSafe),
            'actions' => self::uniqueActions($actions),
            'list' => $list,
            'flow_reply' => $flowReply,
        ];
    }

    /**
     * @param  array<string, mixed>  $interactive
     * @return array{type: string, text: string, media_url: ?string}|null
     */
    private static function buildHeader(array $interactive): ?array
    {
        $header = is_array($interactive['header'] ?? null) ? $interactive['header'] : null;
        if ($header === null) {
            return null;
        }

        $type = strtolower(trim((string) ($header['type'] ?? 'text')));
        $text = trim((string) ($header['text'] ?? ''));
        $mediaUrl = null;

        foreach (['image', 'video', 'document'] as $mediaKey) {
            $media = is_array($header[$mediaKey] ?? null) ? $header[$mediaKey] : null;
            if ($media === null) {
                continue;
            }
            $link = trim((string) ($media['link'] ?? $media['url'] ?? ''));
            if ($link !== '') {
                $mediaUrl = $link;
                if ($type === '' || $type === 'text') {
                    $type = $mediaKey;
                }
                break;
            }
        }

        if ($text === '' && $mediaUrl === null) {
            return null;
        }

        return [
            'type' => $type !== '' ? $type : 'text',
            'text' => $text,
            'media_url' => $mediaUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $interactive
     */
    private static function footerText(array $interactive): string
    {
        $footer = $interactive['footer'] ?? null;
        if (is_array($footer)) {
            return trim((string) ($footer['text'] ?? ''));
        }
        if (is_string($footer)) {
            return trim($footer);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $interactive
     * @return array{
     *     button: string,
     *     sections: list<array{title: string, rows: list<array{title: string, description: string}>}>
     * }|null
     */
    private static function buildListPreview(array $interactive): ?array
    {
        $action = is_array($interactive['action'] ?? null) ? $interactive['action'] : [];
        $sectionsRaw = is_array($action['sections'] ?? null) ? $action['sections'] : [];
        if ($sectionsRaw === []) {
            return null;
        }

        $sections = [];
        foreach ($sectionsRaw as $section) {
            if (! is_array($section)) {
                continue;
            }
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $description = trim((string) ($row['description'] ?? ''));
                if ($title === '' && $description === '') {
                    continue;
                }
                $rows[] = [
                    'title' => $title,
                    'description' => $description,
                ];
            }
            if ($rows === []) {
                continue;
            }
            $sections[] = [
                'title' => trim((string) ($section['title'] ?? '')),
                'rows' => $rows,
            ];
        }

        if ($sections === []) {
            return null;
        }

        $button = trim((string) ($action['button'] ?? $interactive['buttonText'] ?? $interactive['button_text'] ?? 'View options'));
        if ($button === '') {
            $button = 'View options';
        }

        return [
            'button' => $button,
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $interactive
     * @return array{text: string, type: string}|null
     */
    private static function ctaAction(array $interactive): ?array
    {
        $action = is_array($interactive['action'] ?? null) ? $interactive['action'] : [];
        $name = strtolower(trim((string) ($action['name'] ?? $interactive['type'] ?? '')));
        if (! in_array($name, ['cta_url', 'cta', 'call_to_action'], true)) {
            return null;
        }

        $parameters = is_array($action['parameters'] ?? null) ? $action['parameters'] : [];
        $text = trim((string) ($parameters['display_text'] ?? $parameters['displayText'] ?? 'Open'));
        if ($text === '') {
            $text = 'Open';
        }

        return [
            'text' => $text,
            'type' => 'cta_url',
        ];
    }

    /**
     * @param  array<string, mixed>  $interactive
     * @return array{text: string, type: string}|null
     */
    private static function flowCtaAction(array $interactive): ?array
    {
        $action = is_array($interactive['action'] ?? null) ? $interactive['action'] : [];
        $name = strtolower(trim((string) ($action['name'] ?? '')));
        $type = strtolower(trim((string) ($interactive['type'] ?? '')));
        if ($name !== 'flow' && $type !== 'flow') {
            return null;
        }

        $parameters = is_array($action['parameters'] ?? null) ? $action['parameters'] : [];
        $text = trim((string) ($parameters['flow_cta'] ?? $parameters['flowCta'] ?? 'Open'));
        if ($text === '') {
            $text = 'Open';
        }

        return [
            'text' => $text,
            'type' => 'flow',
        ];
    }

    private static function normalizeActionType(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'phone', 'phone_number', 'call' => 'phone',
            'url', 'cta_url', 'cta', 'call_to_action' => 'cta_url',
            'flow' => 'flow',
            'list' => 'list',
            'copy_code' => 'copy_code',
            default => 'quick_reply',
        };
    }

    /**
     * @param  list<array{text: string, type: string}>  $actions
     * @return list<array{text: string, type: string}>
     */
    private static function uniqueActions(array $actions): array
    {
        $seen = [];
        $out = [];
        foreach ($actions as $action) {
            $key = strtolower($action['type'].'|'.$action['text']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $action;
        }

        return $out;
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
