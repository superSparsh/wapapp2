<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PopulateRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.contact_id' => ['nullable', 'integer'],
            'recipients.*.contact_phone' => ['required', 'string', 'max:32'],
            'recipients.*.variable_values' => ['nullable', 'array'],
        ];
    }
}
