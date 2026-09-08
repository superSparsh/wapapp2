<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\User;

class ApiDocumentationService
{
    /** @return list<array<string, mixed>> */
    public function sections(?User $user = null): array
    {
        $token = $user?->api_token ?? 'YOUR_API_TOKEN';
        $baseUrl = rtrim((string) config('account.api.base_url'), '/');
        $appUrl = rtrim((string) config('app.url'), '/');

        $replacements = [
            '{api_token}' => $token,
            '{base_url}' => $baseUrl,
            '{app_url}' => $appUrl,
        ];

        return collect(config('api-docs.sections', []))
            ->map(function (array $section) use ($replacements): array {
                $section['functions'] = collect($section['functions'] ?? [])
                    ->map(function (array $function) use ($replacements): array {
                        foreach (['description', 'returns', 'help'] as $field) {
                            if (isset($function[$field])) {
                                $function[$field] = strtr($function[$field], $replacements);
                            }
                        }

                        if (isset($function['example'])) {
                            $examples = is_array($function['example']) ? $function['example'] : [$function['example']];
                            $function['example'] = array_map(
                                fn (string $example) => strtr($example, $replacements),
                                $examples,
                            );
                        }

                        return $function;
                    })
                    ->all();

                return $section;
            })
            ->all();
    }
}
