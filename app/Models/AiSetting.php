<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, returning a default if not found.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Read a boolean setting safely (avoids PHP (bool)"0" === true).
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set a setting value, creating or updating the record.
     */
    public static function set(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) || $value === null ? $value : json_encode($value)],
        );
    }
}
