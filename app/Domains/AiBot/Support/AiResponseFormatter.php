<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Support;

class AiResponseFormatter
{
    /**
     * Format AI response text for WhatsApp delivery.
     * Strips markdown artifacts, truncates to WhatsApp limit, and normalizes whitespace.
     */
    public static function forWhatsApp(string $text): string
    {
        // Strip code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $text) ?? $text;

        // Convert bold **text** to *text* (WhatsApp format)
        $text = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text) ?? $text;

        // Convert italic _text_ (already WhatsApp compatible)
        // Convert strikethrough ~~text~ to ~text~
        $text = preg_replace('/~~(.+?)~~/', '~$1~', $text) ?? $text;

        // Normalize whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        $text = trim($text);

        // WhatsApp message limit is 4096 characters
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3997) . '...';
        }

        return $text;
    }

    /**
     * Remove scheduling / Calendly booking links and leftover CTA fragments from AI replies.
     */
    public static function stripBookingLinks(string $text): string
    {
        $text = preg_replace(
            '#https?://(?:www\.)?calendly\.com/\S+#i',
            '',
            $text
        ) ?? $text;

        // Drop common "book a time… right here:" fragments left after URL removal.
        $text = preg_replace(
            '/\b(?:please\s+)?book\s+a\s+time[^.!?\n]*?(?:right\s+here|here)\s*:?\s*/iu',
            '',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\b(?:you\s+can\s+)?(?:book|schedule)\s+(?:a\s+)?(?:call|meeting|time)[^.!?\n]*?:?\s*$/imu',
            '',
            $text
        ) ?? $text;

        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;

        return trim($text);
    }
}
