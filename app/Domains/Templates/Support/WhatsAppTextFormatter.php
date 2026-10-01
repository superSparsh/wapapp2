<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

/**
 * Renders WhatsApp / legacy template body markers as HTML for previews,
 * and normalizes legacy markers for Meta / WhatsApp API payloads.
 *
 * Supported markers (same as WhatsApp + legacy caret bold):
 * - *bold* or ^bold^
 * - _italic_
 * - ~strikethrough~
 * - ```monospace```
 */
final class WhatsAppTextFormatter
{
    /**
     * Convert legacy caret bold (^text^) to WhatsApp bold (*text*).
     * Meta / WhatsApp only recognize asterisk bold - caret is preview-only legacy UI.
     */
    public static function toWhatsAppApi(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $converted = preg_replace('/\^([^\^\n]+)\^/u', '*$1*', $text);

        return is_string($converted) ? $converted : $text;
    }

    public static function toHtml(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $html = e($text);

        // Monospace first so inner markers are not reformatted.
        $html = preg_replace('/```([^`]+)```/u', '<code class="wa-mono">$1</code>', $html) ?? $html;

        // Bold: WhatsApp (*) and legacy (^)
        $html = preg_replace('/\*([^*\n]+)\*/u', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\^([^\^\n]+)\^/u', '<strong>$1</strong>', $html) ?? $html;

        // Italic: _text_ (avoid matching lone underscores inside words when possible)
        $html = preg_replace('/(?<![A-Za-z0-9])_([^_\n]+)_(?![A-Za-z0-9])/u', '<em>$1</em>', $html) ?? $html;

        // Strikethrough
        $html = preg_replace('/~([^~\n]+)~/u', '<del>$1</del>', $html) ?? $html;

        return nl2br($html, false);
    }
}
