<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveLtoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_introduction' => ['required', 'string', 'max:60'],
            'expiration_time' => ['nullable', 'boolean'],
            'time_variable' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'coupon_code' => ['nullable', 'string', 'max:15'],
        ];
    }
}
