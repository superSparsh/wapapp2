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
}
