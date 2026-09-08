<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Lightweight feature flag system.
 *
 * Flags are defined in config/features.php and can be toggled at runtime
 * via the cache (database or Redis). This avoids adding an external
 * package dependency while still supporting gradual rollouts.
 *
 * Usage:
 *   // Check a flag
 *   if (app(FeatureFlag::class)->isEnabled('chatbot.enhanced_conditions')) {
 *       // ...
 *   }
 *
 *   // Enable/disable at runtime
 *   app(FeatureFlag::class)->enable('chatbot.enhanced_conditions');
 *   app(FeatureFlag::class)->disable('chatbot.enhanced_conditions');
 */
class FeatureFlag
{
    /**
     * Check if a feature is enabled.
     *
     * Resolution order:
     * 1. Runtime cache override (enable/disable calls)
     * 2. Environment variable (FEATURE_{NAME}=true)
     * 3. Config default (config/features.php)
     */
    public function isEnabled(string $feature): bool
    {
        // Check runtime cache override first
        $cacheKey = $this->cacheKey($feature);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return (bool) $cached;
        }

        // Check environment variable (FEATURE_CHATBOT_ENHANCED_CONDITIONS)
        $envKey = 'FEATURE_'.strtoupper(str_replace(['.', '-'], '_', $feature));
        $envValue = env($envKey);

        if ($envValue !== null) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }

        // Fall back to config default
        return (bool) Config::get("features.{$feature}.default", false);
    }

    /**
     * Check if a feature is disabled (convenience method).
     */
    public function isDisabled(string $feature): bool
    {
        return ! $this->isEnabled($feature);
    }

    /**
     * Enable a feature at runtime (persisted in cache).
     *
     * @param  string  $feature  Dot-notation feature name
     * @param  int|null  $ttl  Time-to-live in seconds (null = forever/until cache clear)
     */
    public function enable(string $feature, ?int $ttl = null): void
    {
        Cache::put($this->cacheKey($feature), true, $ttl);
    }

    /**
     * Disable a feature at runtime.
     */
    public function disable(string $feature, ?int $ttl = null): void
    {
        Cache::put($this->cacheKey($feature), false, $ttl);
    }

    /**
     * Reset a feature to its config-defined default (remove cache override).
     */
    public function reset(string $feature): void
    {
        Cache::forget($this->cacheKey($feature));
    }

    /**
     * Get all defined features with their current status.
     */
    public function all(): array
    {
        $definitions = Config::get('features', []);
        $result = [];

        foreach ($definitions as $group => $flags) {
            if (! is_array($flags)) {
                continue;
            }

            foreach ($flags as $flag => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $name = "{$group}.{$flag}";
                $result[$name] = [
                    'enabled' => $this->isEnabled($name),
                    'description' => $config['description'] ?? '',
                    'default' => $config['default'] ?? false,
                ];
            }
        }

        return $result;
    }

    /**
     * Get all currently enabled features.
     */
    public function enabled(): array
    {
        return array_keys(array_filter($this->all(), fn (array $f) => $f['enabled']));
    }

    private function cacheKey(string $feature): string
    {
        return 'feature_flag:'.str_replace('.', ':', $feature);
    }
}
