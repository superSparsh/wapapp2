<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,20}$/', 'unique:contacts,phone'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:191'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'mail_list_id' => ['nullable', 'integer', 'exists:mail_lists,id'],
            'source' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '';
        $countryCode = preg_replace('/\D+/', '', (string) $this->input('country_code', '')) ?? '';

        if ($phone !== '' && $countryCode !== '' && ! str_starts_with($phone, $countryCode)) {
            $phone = $countryCode.$phone;
        }

        if ($phone !== '') {
            $this->merge(['phone' => $phone]);
        }
    }
}
