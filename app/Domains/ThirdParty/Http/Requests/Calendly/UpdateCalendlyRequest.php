<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\Calendly;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendlyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings.access_token'    => ['required', 'string', 'min:20'],
            'settings.user_email'      => ['nullable', 'email', 'max:255'],
            'settings.whatsapp_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'settings.enable_whatsapp' => ['nullable', 'in:on'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'settings.access_token.required'        => 'Calendly access token is required.',
            'settings.access_token.min'             => 'Access token must be at least 20 characters.',
            'settings.whatsapp_number.regex'        => 'WhatsApp number must be in international format (e.g. +911234567890).',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'settings.access_token'    => 'access token',
            'settings.user_email'      => 'email',
            'settings.whatsapp_number' => 'WhatsApp number',
        ];
    }
}
