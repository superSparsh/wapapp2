<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'in:15,30,45,60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_minutes.in' => 'Duration must be 15, 30, 45, or 60 minutes.',
        ];
    }
}
