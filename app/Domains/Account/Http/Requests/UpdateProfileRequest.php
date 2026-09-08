<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user('web');

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'timezone' => ['required', 'string', Rule::in(array_keys(config('account.timezones', [])))],
            'country_code' => ['required', 'string', Rule::in(array_keys(config('account.countries', [])))],
            'locale' => ['required', 'string', Rule::in(array_keys(config('account.locales', [])))],
            'password' => ['nullable', 'confirmed', Password::min(8)->numbers()],
            'avatar' => ['nullable', 'image', 'mimes:'.implode(',', config('account.avatar.mimes', ['jpg', 'png'])), 'max:'.((int) config('account.avatar.max_kb', 2048))],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }
}
