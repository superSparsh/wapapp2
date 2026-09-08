<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the public "Line Login" form:
 *  - phone: the WhatsApp number (digits-only or with +/spaces, required)
 *  - password: the Number Access password set by the account owner (required)
 */
class LineLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public — no auth required
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone'    => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ];
    }
}
