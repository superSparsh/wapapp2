<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_email' => ['required', 'email', 'max:191'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'mobile' => ['required', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'new_password' => ['required', Password::min(8)->numbers()],
            'business_name' => ['required', 'string', 'max:120'],
            'business_website' => ['nullable', 'url', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
