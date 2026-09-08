<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLineProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'website' => ['required', 'url', 'max:500'],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:2000'],
            'about' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
