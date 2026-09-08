<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'audience_id' => ['nullable', 'integer'],
            'whatsapp_line_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'template_variables' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
