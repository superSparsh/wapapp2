<?php

declare(strict_types=1);

namespace App\Support;

final class DataDeletionConfig
{
    /** @return array<string, string> */
    public static function modules(): array
    {
        return config('billing.data_deletion.modules', []);
    }

    /** @return array<string, string> */
    public static function dataAgeLabels(): array
    {
        return self::labels(config('billing.data_deletion.data_age_options', []));
    }

    /** @return array<string, string> */
    public static function scheduleLabels(): array
    {
        return self::labels(config('billing.data_deletion.schedule_options', []));
    }

    public static function dataAgeDays(string $key): int
    {
        return self::days(config('billing.data_deletion.data_age_options', []), $key, 30);
    }

    public static function scheduleDays(string $key): int
    {
        return self::days(config('billing.data_deletion.schedule_options', []), $key, 1);
    }

    public static function exportRetentionDays(): int
    {
        return (int) config('billing.data_deletion.export_retention_days', 30);
    }

    /** @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private static function labels(array $options): array
    {
        return collect($options)->mapWithKeys(function (mixed $value, string $key): array {
            if (is_array($value)) {
                return [$key => (string) ($value['label'] ?? $key)];
            }

            return [$key => (string) $value];
        })->all();
    }

    /** @param  array<string, mixed>  $options */
    private static function days(array $options, string $key, int $default): int
    {
        $option = $options[$key] ?? null;

        if (is_array($option) && isset($option['days'])) {
            return (int) $option['days'];
        }

        return $default;
    }
}
