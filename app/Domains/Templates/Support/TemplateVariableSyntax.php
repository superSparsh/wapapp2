<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

final class TemplateVariableSyntax
{
  /**
   * Legacy placeholder format: $(variable_name)
   */
  public static function placeholder(string $name): string
  {
    return '$('.$name.')';
  }

  /**
   * Normalize any pasted {{name}} placeholders to legacy $(name).
   */
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

  /**
   * Replace $(name) / {{name}} placeholders with mapped values.
   * Missing or empty values keep the legacy placeholder form $(name).
   *
   * @param  array<string, scalar|null>  $valuesByName
   */
  public static function substitute(string $text, array $valuesByName): string
  {
    $normalized = self::normalizeBodyText($text);

    $replaced = preg_replace_callback(
      '/\$\(([a-zA-Z0-9_]+)\)/',
      static function (array $matches) use ($valuesByName): string {
        $name = $matches[1];
        if (! array_key_exists($name, $valuesByName)) {
          return self::placeholder($name);
        }

        $value = $valuesByName[$name];
        if ($value === null || $value === '') {
          return self::placeholder($name);
        }

        return (string) $value;
      },
      $normalized,
    );

    return is_string($replaced) ? $replaced : $normalized;
  }

  /**
   * Campaign / template preview: always show $(variable_name), never sample values.
   */
  public static function previewText(string $text): string
  {
    return self::normalizeBodyText($text);
  }

  /**
   * Zip ordered sample values onto extracted variable names.
   *
   * @param  list<string>  $names
   * @param  list<mixed>  $samples
   * @return array<string, string>
   */
  public static function mapSamplesToNames(array $names, array $samples): array
  {
    $mapped = [];

    foreach (array_values($names) as $index => $name) {
      if (! is_string($name) || $name === '') {
        continue;
      }

      $sample = $samples[$index] ?? null;
      $mapped[$name] = is_scalar($sample) ? (string) $sample : '';
    }

    return $mapped;
  }
}
