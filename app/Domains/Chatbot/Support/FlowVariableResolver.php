<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

use App\Models\Contact;
use App\Models\Conversation;
use Carbon\Carbon;

class FlowVariableResolver
{
    /**
     * Replace $(variable) and {{variable}} placeholders (legacy + curly syntax).
     *
     * @param  array<string, mixed>  $variables
     */
    public function resolve(string $text, array $variables): string
    {
        if ($text === '' || $variables === []) {
            return $text;
        }

        if (! str_contains($text, '$(') && ! str_contains($text, '{{')) {
            return $text;
        }

        $replace = function (array $matches) use ($variables): string {
            $key = $matches[1];

            return $this->resolveNested($key, $variables) ?? $matches[0];
        };

        // Legacy chatbot / BuiltinVariableCatalog syntax: $(first_name)
        if (str_contains($text, '$(')) {
            $text = (string) preg_replace_callback(
                '/\$\(\s*([\w.]+)\s*\)/',
                $replace,
                $text,
            );
        }

        // Curly syntax: {{first_name}}
        if (str_contains($text, '{{')) {
            $text = (string) preg_replace_callback(
                '/\{\{\s*([\w.]+)\s*\}\}/',
                $replace,
                $text,
            );
        }

        return $text;
    }

    /**
     * Merge conversation / contact / datetime context under flow variables.
     * Flow-set keys (user_response, custom vars) win over context.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function withConversationContext(array $variables, Conversation $conversation): array
    {
        $context = $this->conversationContext($conversation);

        $messageText = (string) ($variables['message_text'] ?? $variables['_last_reply'] ?? $variables['user_response'] ?? '');
        if ($messageText !== '') {
            $context['message_text'] = $messageText;
        }

        return array_merge($context, $variables);
    }

    /**
     * Built-in variables available to every chatbot message (legacy parity).
     *
     * @return array<string, mixed>
     */
    public function conversationContext(Conversation $conversation): array
    {
        $fullName = trim((string) ($conversation->contact_name ?? ''));
        $phone = (string) ($conversation->contact_phone ?? '');
        $linePhone = (string) ($conversation->line_phone ?? '');

        $contact = null;
        if ($conversation->relationLoaded('contact')) {
            $contact = $conversation->contact;
        } elseif (! empty($conversation->contact_id)) {
            $contact = $conversation->contact;
        }

        if ($contact instanceof Contact) {
            $contactName = trim((string) ($contact->name ?? ''));
            if ($fullName === '' && $contactName !== '') {
                $fullName = $contactName;
            }
        }

        [$firstName, $lastName] = $this->splitName($fullName);

        $subscriberFull = $contact instanceof Contact
            ? (trim((string) ($contact->name ?? '')) ?: $fullName)
            : $fullName;
        [$subscriberFirst, $subscriberLast] = $this->splitName($subscriberFull);

        $now = Carbon::now();

        $context = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'display_name' => $fullName !== '' ? $fullName : $phone,
            'phone_number' => $phone,
            'recipient_number' => $linePhone,
            'subscriber_first_name' => $subscriberFirst,
            'subscriber_last_name' => $subscriberLast,
            'subscriber_full_name' => $subscriberFull,
            'subscriber_email' => $contact instanceof Contact ? (string) ($contact->email ?? '') : '',
            'subscriber_uid' => $contact instanceof Contact ? (string) $contact->id : '',
            'subscriber_status' => $contact instanceof Contact
                ? (string) ($contact->status?->value ?? $contact->opt_in_status?->value ?? '')
                : '',
            'subscriber_created_at' => $contact instanceof Contact && $contact->created_at
                ? $contact->created_at->toDateTimeString()
                : '',
            'subscriber_updated_at' => $contact instanceof Contact && $contact->updated_at
                ? $contact->updated_at->toDateTimeString()
                : '',
            'email' => $contact instanceof Contact ? (string) ($contact->email ?? '') : '',
            'current_date' => $now->format('Y-m-d'),
            'current_time' => $now->format('H:i:s'),
            'current_datetime' => $now->format('Y-m-d H:i:s'),
            'current_day' => $now->format('l'),
            'current_month' => $now->format('F'),
            'current_year' => $now->format('Y'),
            'current_timestamp' => (string) $now->timestamp,
            'timezone' => $now->timezone->getName(),
        ];

        return $context;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [$fullName];

        return [
            (string) ($parts[0] ?? ''),
            (string) ($parts[1] ?? ''),
        ];
    }

    /**
     * Resolve a potentially dot-notated key from a flat or nested array.
     *
     * @param  array<string, mixed>  $variables
     */
    private function resolveNested(string $key, array $variables): ?string
    {
        if (array_key_exists($key, $variables)) {
            return $this->stringify($variables[$key]);
        }

        $segments = explode('.', $key);
        /** @var mixed $current */
        $current = $variables;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $this->stringify($current);
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return null;
    }
}
