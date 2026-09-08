<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:191'],
            'header' => ['nullable', 'string', 'max:255'],
            'paragraph' => ['nullable', 'string', 'max:1024'],
            'input' => ['nullable', 'string', 'max:255'],
            'dropdown' => ['nullable', 'string', 'max:255'],
            'checkbox' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'WhatsApp number is required.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
