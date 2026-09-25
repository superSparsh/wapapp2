<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Template;
use App\Models\WhatsappLine;

/**
 * Auto-fill CAMS TemplateParams for Intent Response / trigger sends
 * from conversation contact context (same idea as chatbot template nodes).
 */
class TriggerTemplateParamsResolver
{
    public function __construct(
        private readonly TemplateRegistryService $registry,
        private readonly TemplatePreviewService $previewService,
    ) {}

    /**
     * @return array<string, string>
     */
    public function forConversation(Conversation $conversation, string $templateCode): array
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            return [];
        }

        $line = $conversation->whatsapp_line_id
            ? WhatsappLine::query()->find((int) $conversation->whatsapp_line_id)
            : null;

        $template = $this->registry->findForSend($templateCode, $line instanceof WhatsappLine ? $line : null);
        if ($template === null) {
            $template = Template::query()
                ->where('code', $templateCode)
                ->orderByDesc('id')
                ->first();
        }

        if ($template === null) {
            return [];
        }

        $variableNames = collect($this->previewService->variablesForTemplate($template))
            ->map(fn (array $variable): string => trim((string) ($variable['name'] ?? '')))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($variableNames === []) {
            return [];
        }

        $conversation->loadMissing('contact');
        $contact = $conversation->contact;
        $phone = trim((string) ($conversation->contact_phone ?? $contact?->phone ?? ''));
        $context = $this->context($conversation, $contact, $phone);

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
     * @return list<string>
     */
    public function variableNamesForCode(string $templateCode): array
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            return [];
        }

        $template = $this->registry->findForSend($templateCode)
            ?? Template::query()->where('code', $templateCode)->orderByDesc('id')->first();

        if ($template === null) {
            return [];
        }

        return collect($this->previewService->variablesForTemplate($template))
            ->map(fn (array $variable): string => trim((string) ($variable['name'] ?? '')))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function context(Conversation $conversation, ?Contact $contact, string $phone): array
    {
        $fullName = trim((string) ($contact?->name ?? $conversation->contact_name ?? ''));
        $parts = preg_split('/\s+/', $fullName, 2) ?: [];
        $firstName = trim((string) ($parts[0] ?? ''));
        $lastName = trim((string) ($parts[1] ?? ''));

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
            'email' => filled($contact?->email) ? (string) $contact->email : null,
        ], static fn ($value) => $value !== null && $value !== '');

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
