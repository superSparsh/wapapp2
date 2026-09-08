<?php

declare(strict_types=1);

namespace App\Support;

final class TemplateVariableSyntax
{
    public static function placeholder(string $name): string
    {
        return '$('.$name.')';
    }

    public static function normalizeBodyText(string $text): string
    {
        $normalized = preg_replace('/\{\{([a-zA-Z0-9_]+)\}\}/', '$(\1)', $text);

        return is_string($normalized) ? $normalized : $text;
    }

    /**
     * @return list<string>
     */
    public static function extractVariableNames(string $body): array
    {
        $normalized = self::normalizeBodyText($body);

        preg_match_all('/\$\(([a-zA-Z0-9_]+)\)/', $normalized, $matches);

        return collect($matches[1] ?? [])->unique()->values()->all();
    }
}
