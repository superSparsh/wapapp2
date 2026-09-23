<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Filters Horizon supervisors by HORIZON_ROLE so web and OCI hosts
 * never steal each other's queues.
 */
final class HorizonRole
{
    /** @var list<string> */
    public const LIGHT = [
        'critical',
        'messages',
        'automation',
        'default',
        'low',
        'ai',
        'chatbot',
        'provisioning',
    ];

    /** @var list<string> */
    public const HEAVY = [
        'campaign',
        'status',
        'import',
    ];

    public static function current(): string
    {
        $role = strtolower(trim((string) config('oci-workers.horizon_role', env('HORIZON_ROLE', 'all'))));

        return in_array($role, ['web', 'oci-heavy', 'all'], true) ? $role : 'all';
    }

    /**
     * @return list<string>
     */
    public static function allowedSupervisors(?string $role = null): array
    {
        return match ($role ?? self::current()) {
            'web' => self::LIGHT,
            'oci-heavy' => self::HEAVY,
            default => array_values(array_unique([...self::LIGHT, ...self::HEAVY])),
        };
    }

    /**
     * Keep only supervisors allowed for the active role in each Horizon environment.
     *
     * @param  array<string, mixed>  $horizon
     * @return array<string, mixed>
     */
    public static function filterConfig(array $horizon, ?string $role = null): array
    {
        $allowed = array_flip(self::allowedSupervisors($role));

        if (isset($horizon['defaults']) && is_array($horizon['defaults'])) {
            $horizon['defaults'] = array_intersect_key($horizon['defaults'], $allowed);
        }

        if (isset($horizon['environments']) && is_array($horizon['environments'])) {
            foreach ($horizon['environments'] as $env => $supervisors) {
                if (! is_array($supervisors)) {
                    continue;
                }
                $horizon['environments'][$env] = array_intersect_key($supervisors, $allowed);
            }
        }

        return $horizon;
    }
}
