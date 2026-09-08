<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'audience_id' => ['nullable', 'integer'],
            'whatsapp_line_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'template_variables' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.contact_id' => ['nullable', 'integer'],
            'recipients.*.contact_phone' => ['required_with:recipients', 'string', 'max:32'],
            'recipients.*.variable_values' => ['nullable', 'array'],
        ];
    }
}
