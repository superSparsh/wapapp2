<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Requests;

use App\Enums\NotificationContactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncNotificationContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    protected function prepareForValidation(): void
    {
        $contacts = collect($this->input('contacts', []))
            ->filter(function (mixed $row): bool {
                if (! is_array($row)) {
                    return false;
                }

                if (! empty($row['id'])) {
                    return true;
                }

                $fullName = trim((string) ($row['full_name'] ?? ''));
                $contactInfo = trim((string) ($row['contact_info'] ?? ''));

                return $fullName !== '' || $contactInfo !== '';
            })
            ->values()
            ->all();

        $this->merge(['contacts' => $contacts]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alerts_enabled' => ['required', 'boolean'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*.full_name' => ['required', 'string', 'max:255'],
            'contacts.*.type' => ['required', 'string', Rule::in(array_column(NotificationContactType::cases(), 'value'))],
            'contacts.*.contact_info' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contacts.*.full_name.required' => 'Please enter a full name.',
            'contacts.*.full_name.max' => 'Full name is too long.',
            'contacts.*.type.required' => 'Please choose a contact type.',
            'contacts.*.type.in' => 'Contact type must be email or WhatsApp.',
            'contacts.*.contact_info.required' => 'Please enter an email or WhatsApp number.',
            'contacts.*.contact_info.max' => 'Contact info is too long.',
        ];
    }
}
