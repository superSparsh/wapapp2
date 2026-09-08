<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetLinePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'line_id'               => ['required', 'integer', 'min:1'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
