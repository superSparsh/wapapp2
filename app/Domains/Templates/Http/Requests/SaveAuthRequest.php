<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'copy_button_text' => ['required', 'string', 'max:25'],
            'auto_fill' => ['nullable', 'boolean'],
            'zero_tap' => ['nullable', 'boolean'],
            'fill_button_text' => ['nullable', 'string', 'max:25'],
            'message_validity' => ['nullable', 'boolean'],
            'validity_seconds' => ['nullable', 'integer', 'min:30', 'max:900'],
            'expiration_time' => ['nullable', 'boolean'],
            'expiration_minutes' => ['nullable', 'integer', 'min:1', 'max:90'],
            'add_secret_recommendation' => ['nullable', 'boolean'],
            'supported_apps' => ['nullable', 'array', 'max:55'],
            'supported_apps.*.package_name' => ['nullable', 'string', 'max:255'],
            'supported_apps.*.signature_hash' => ['nullable', 'string', 'max:255'],
        ];
    }
}
