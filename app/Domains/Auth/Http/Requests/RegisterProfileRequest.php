<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterProfileRequest extends FormRequest
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
            'whatsapp_addendum' => ['accepted'],
            'age_confirmed' => ['accepted'],
            'collection_notice_acknowledged' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        $minAge = (int) config('legal.children.minimum_age', 18);

        return [
            'age_confirmed.accepted' => "You must confirm you are at least {$minAge} years old.",
            'collection_notice_acknowledged.accepted' => 'Please acknowledge the personal data collection notice.',
        ];
    }
}
