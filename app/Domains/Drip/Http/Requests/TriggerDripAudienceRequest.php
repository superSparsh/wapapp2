<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerDripAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Enter a valid phone number (7–20 digits, optional + prefix).',
        ];
    }
}
