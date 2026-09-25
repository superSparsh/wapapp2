<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

use Illuminate\Support\Str;

/**
 * Human-readable activity copy for team-member module mutations.
 */
final class TeamModuleActivityLabel
{
    public static function describe(string $verb, string $routeName): string
    {
        $verb = strtoupper($verb);

        if ($mapped = self::mappedDescription($verb, $routeName)) {
            return $mapped;
        }

        $subject = self::humanizeRoute($routeName);

        return match ($verb) {
            'POST' => 'Submitted '.$subject,
            'PUT', 'PATCH' => 'Updated '.$subject,
            'DELETE' => 'Deleted '.$subject,
            default => $verb.' '.$subject,
        };
    }

    /**
     * @return array{0: string, 1: string}|null [verb, routeName]
     */
    public static function parseAction(string $action): ?array
    {
        if (preg_match('/^team\.module\.(POST|PUT|PATCH|DELETE)\.(.+)$/i', $action, $matches) !== 1) {
            return null;
        }

        return [strtoupper($matches[1]), $matches[2]];
    }

    private static function mappedDescription(string $verb, string $routeName): ?string
    {
        $labels = [
            'templates.builder.submit.save' => [
                'POST' => 'Submitted template for WhatsApp approval',
            ],
            'templates.builder.body.save' => [
                'POST' => 'Saved template body',
                'PUT' => 'Updated template body',
                'PATCH' => 'Updated template body',
            ],
            'templates.builder.header.save' => [
                'POST' => 'Saved template header',
            ],
            'templates.builder.footer.save' => [
                'POST' => 'Saved template footer',
            ],
            'templates.builder.buttons.save' => [
                'POST' => 'Saved template buttons',
            ],
            'templates.builder.auth.save' => [
                'POST' => 'Saved authentication template settings',
            ],
            'templates.builder.lto.save' => [
                'POST' => 'Saved limited-time offer settings',
            ],
            'templates.builder.carousel.save' => [
                'POST' => 'Saved carousel template',
            ],
            'templates.builder.body-media.save' => [
                'POST' => 'Saved template media',
            ],
            'templates.destroy' => [
                'DELETE' => 'Deleted a template',
            ],
            'templates.duplicate' => [
                'POST' => 'Duplicated a template',
            ],
            'templates.free.store' => [
                'POST' => 'Created a free-form template',
            ],
            'templates.variables.store' => [
                'POST' => 'Created a template variable',
            ],
            'templates.variables.update' => [
                'PUT' => 'Updated a template variable',
                'PATCH' => 'Updated a template variable',
            ],
            'templates.variables.destroy' => [
                'DELETE' => 'Deleted a template variable',
            ],
        ];

        return $labels[$routeName][$verb] ?? null;
    }

    private static function humanizeRoute(string $routeName): string
    {
        $trimmed = preg_replace('/\.(save|store|update|destroy|create|edit|index)$/', '', $routeName) ?? $routeName;

        return (string) Str::of($trimmed)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title();
    }
}
