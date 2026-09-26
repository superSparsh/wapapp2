<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    protected function prepareForValidation(): void
    {
        // Leave password blank = keep current. Drop empty values so confirm is not required.
        if (blank($this->input('password'))) {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user('web');
        $changingPassword = filled($this->input('password'));

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'timezone' => ['required', 'string', Rule::in(array_keys(config('account.timezones', [])))],
            'country_code' => ['required', 'string', Rule::in(array_keys(config('account.countries', [])))],
            'locale' => ['required', 'string', Rule::in(array_keys(config('account.locales', [])))],
            'password' => [
                'nullable',
                'string',
                ...($changingPassword ? [Password::min(8)->numbers()] : []),
            ],
            'password_confirmation' => [
                Rule::requiredIf($changingPassword),
                'nullable',
                'string',
                'same:password',
            ],
            'avatar' => [
                'nullable',
                'image',
                'mimes:'.implode(',', config('account.avatar.mimes', ['jpg', 'png'])),
                'max:'.((int) config('account.avatar.max_kb', 2048)),
            ],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $maxMb = $this->avatarMaxMb();
        $mimes = strtoupper(implode(', ', config('account.avatar.mimes', ['jpg', 'png', 'webp'])));

        return [
            'password_confirmation.required' => 'Please confirm your new password.',
            'password_confirmation.same' => 'New password and confirmation do not match.',
            'avatar.image' => 'Avatar must be a valid image file.',
            'avatar.mimes' => "Avatar must be one of: {$mimes}.",
            'avatar.max' => "Avatar must not be greater than {$maxMb} MB.",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $maxMb = $this->avatarMaxMb();

            // PHP rejected the upload (too large for upload_max_filesize) — Laravel then
            // sees no file, so "nullable" would silently skip the avatar change.
            $fileError = $_FILES['avatar']['error'] ?? null;
            if (in_array($fileError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $validator->errors()->add(
                    'avatar',
                    "Avatar must not be greater than {$maxMb} MB."
                );
            }
        });
    }

    private function avatarMaxMb(): string
    {
        $mb = ((int) config('account.avatar.max_kb', 2048)) / 1024;

        return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.') ?: '2';
    }
}
