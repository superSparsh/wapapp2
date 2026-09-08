<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

class FlowVariableResolver
{
    /**
     * Replace {{variable}} placeholders in a string with values from the context.
     *
     * @param  array<string, mixed>  $variables
     */
    public function resolve(string $text, array $variables): string
    {
        if ($variables === [] || ! str_contains($text, '{{')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];

                return $this->resolveNested($key, $variables) ?? $matches[0];
            },
            $text,
        );
    }

    /**
     * Resolve a potentially dot-notated key from a flat or nested array.
     *
     * @param  array<string, mixed>  $variables
     */
    private function resolveNested(string $key, array $variables): ?string
    {
        // Direct lookup first
        if (array_key_exists($key, $variables)) {
            return $this->stringify($variables[$key]);
        }

        // Dot-notation lookup
        $segments = explode('.', $key);
        /** @var mixed $current */
        $current = $variables;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $this->stringify($current);
    }

    private function stringify(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return null;
    }
}
