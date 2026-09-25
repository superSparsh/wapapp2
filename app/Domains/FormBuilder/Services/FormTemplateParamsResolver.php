<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Services;

use App\Domains\Templates\Services\TemplatePreviewService;
use App\Models\Contact;
use App\Models\Template;

/**
 * Auto-fill CAMS TemplateParams for form-builder sends from the template's
 * declared placeholders + submission/contact context (campaigns / triggers parity).
 */
class FormTemplateParamsResolver
{
    public function __construct(
        private readonly TemplatePreviewService $previewService,
    ) {}

    /**
     * @param  array<string, mixed>  $submissionData
     * @return array<string, string>
     */
    public function forSubmission(
        Template $template,
        array $submissionData,
        ?Contact $contact,
        string $phone,
    ): array {
        $variableNames = collect($this->previewService->variablesForTemplate($template))
            ->map(fn (array $variable): string => trim((string) ($variable['name'] ?? '')))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($variableNames === []) {
            return [];
        }

        $context = $this->context($submissionData, $contact, $phone);

        $params = [];
        foreach ($variableNames as $name) {
            $value = $this->lookup($name, $context);
            if ($value === '') {
                $value = $this->fallback($name, $context);
            }
            $params[$name] = $value;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $submissionData
     * @return array<string, string>
     */
    private function context(array $submissionData, ?Contact $contact, string $phone): array
    {
        $fromSubmission = [];
        foreach ($submissionData as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                $fromSubmission[$name] = $stringValue;
            }
        }

        $firstName = trim((string) ($fromSubmission['first_name'] ?? $contact?->custom_fields['FIRST_NAME'] ?? ''));
        $lastName = trim((string) ($fromSubmission['last_name'] ?? $contact?->custom_fields['LAST_NAME'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);
        if ($fullName === '') {
            $fullName = trim((string) ($contact?->name ?? $fromSubmission['name'] ?? ''));
        }
        if ($firstName === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $firstName = trim((string) ($parts[0] ?? ''));
            $lastName = trim((string) ($parts[1] ?? ''));
        }

        $context = array_filter([
            'full_name' => $fullName !== '' ? $fullName : null,
            'name' => $fullName !== '' ? $fullName : null,
            'user_name' => $fullName !== '' ? $fullName : null,
            'display_name' => $fullName !== '' ? $fullName : null,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'phone' => $phone !== '' ? $phone : null,
            'phone_number' => $phone !== '' ? $phone : null,
            'user_phone' => $phone !== '' ? $phone : null,
            'mobile' => $phone !== '' ? $phone : null,
            'email' => filled($contact?->email)
                ? (string) $contact->email
                : ($fromSubmission['email'] ?? null),
            // Campaigns / legacy: unsubscribe placeholder expects contact id (or stable token).
            'unsub' => (string) ($contact?->id ?? 'unsub'),
        ], static fn ($value) => $value !== null && $value !== '');

        foreach ($fromSubmission as $key => $value) {
            if (! array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }

        $customFields = is_array($contact?->custom_fields) ? $contact->custom_fields : [];
        foreach ($customFields as $key => $value) {
            if (! is_string($key) || $key === '' || array_key_exists($key, $context)) {
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $stringValue = trim((string) ($value ?? ''));
            if ($stringValue !== '') {
                $context[$key] = $stringValue;
            }
        }

        return $context;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function lookup(string $name, array $context): string
    {
        $aliases = match ($name) {
            'name', 'full_name', 'user_name', 'display_name' => [
                'full_name', 'name', 'user_name', 'display_name',
            ],
            'first_name' => ['first_name'],
            'last_name' => ['last_name'],
            'phone', 'phone_number', 'user_phone', 'mobile' => [
                'phone_number', 'phone', 'user_phone', 'mobile',
            ],
            'email' => ['email'],
            'unsub' => ['unsub'],
            default => [$name],
        };

        foreach ($aliases as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $context
     */
    private function fallback(string $name, array $context): string
    {
        if ($name === 'unsub') {
            return (string) ($context['unsub'] ?? 'unsub');
        }

        $phone = trim((string) ($context['phone_number'] ?? $context['phone'] ?? ''));
        $fullName = trim((string) ($context['full_name'] ?? $context['name'] ?? ''));

        if (in_array($name, ['phone', 'phone_number', 'user_phone', 'mobile'], true) && $phone !== '') {
            return $phone;
        }

        if ($fullName !== '') {
            return $fullName;
        }

        if ($phone !== '') {
            return $phone;
        }

        // CAMS rejects empty TemplateParams when placeholders exist.
        return '-';
    }
}
