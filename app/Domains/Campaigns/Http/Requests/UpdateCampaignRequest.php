<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Requests;

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
            'name' => ['sometimes', 'string', 'max:255'],
            'audience_id' => ['nullable', 'integer', 'exists:mail_lists,id'],
            'whatsapp_line_id' => ['nullable', 'integer', 'exists:whatsapp_lines,id'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['string'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
