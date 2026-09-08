<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInboxContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'line_id' => ['required', 'integer'],
            'line_phone' => ['nullable', 'string', 'max:30'],
            'response_type' => ['nullable', 'string', 'in:human_response,ai_response'],
            'contact_id' => ['nullable', 'integer'],
        ];
    }
}
