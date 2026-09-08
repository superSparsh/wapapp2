<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\Blacklist;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlacklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:191', 'required_without:phone'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
