<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

/**
 * Maps class names, namespaces, CAMS actions, and display names to error modules.
 */
final class ErrorModuleResolver
{
    public function modules(): array
    {
        /** @var array<string, string> $modules */
        $modules = config('admin-error-modules.modules', []);

        return $modules;
    }

    public function label(string $module): string
    {
        return $this->modules()[$module] ?? ucfirst(str_replace('_', ' ', $module));
    }

    public function isValidModule(string $module): bool
    {
        return array_key_exists($module, $this->modules());
    }

    public function fromClass(?string $class): string
    {
        if ($class === null || $class === '') {
            return 'other';
        }

        /** @var array<string, string> $jobClasses */
        $jobClasses = config('admin-error-modules.job_classes', []);
        if (isset($jobClasses[$class])) {
            return $jobClasses[$class];
        }

        /** @var array<string, string> $namespaces */
        $namespaces = config('admin-error-modules.namespaces', []);
        uksort($namespaces, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($namespaces as $prefix => $module) {
            if (str_starts_with($class, $prefix)) {
                return $module;
            }
        }

        return 'other';
    }

    public function fromCamsAction(?string $action): string
    {
        if ($action === null || $action === '') {
            return 'other';
        }

        /** @var array<string, string> $actions */
        $actions = config('admin-error-modules.cams_actions', []);

        return $actions[$action] ?? 'other';
    }

    /**
     * Resolve from a queue payload display name / command class string.
     */
    public function fromDisplayName(?string $displayName): string
    {
        if ($displayName === null || $displayName === '') {
            return 'other';
        }

        if (str_contains($displayName, '\\')) {
            return $this->fromClass($displayName);
        }

        /** @var array<string, string> $jobClasses */
        $jobClasses = config('admin-error-modules.job_classes', []);
        foreach ($jobClasses as $class => $module) {
            if (str_ends_with($class, '\\'.$displayName) || $class === $displayName) {
                return $module;
            }
        }

        return 'other';
    }

    /**
     * Fragments matched against jobs.payload / failed_jobs.payload.
     *
     * @return list<string>
     */
    public function payloadLikeFragments(string $module): array
    {
        $fragments = [];

        /** @var array<string, string> $jobClasses */
        $jobClasses = config('admin-error-modules.job_classes', []);
        foreach ($jobClasses as $class => $mapped) {
            if ($mapped !== $module) {
                continue;
            }
            $fragments[] = $class;
            $fragments[] = str_replace('\\', '\\\\', $class);
            $basename = class_basename($class);
            if ($basename !== '') {
                $fragments[] = $basename;
            }
        }

        /** @var array<string, string> $namespaces */
        $namespaces = config('admin-error-modules.namespaces', []);
        foreach ($namespaces as $prefix => $mapped) {
            if ($mapped !== $module) {
                continue;
            }
            $trimmed = rtrim($prefix, '\\');
            $fragments[] = $trimmed;
            $fragments[] = str_replace('\\', '\\\\', $trimmed);
        }

        return array_values(array_unique(array_filter($fragments)));
    }
}
