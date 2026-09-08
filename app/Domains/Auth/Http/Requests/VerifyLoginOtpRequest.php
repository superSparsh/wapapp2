<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VerifyLoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:6'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! PhoneNormalizer::isValidIndianMobile($this->string('phone')->toString())) {
                $validator->errors()->add('phone', 'Please enter a valid 10-digit mobile number.');
            }
        });
    }
}
