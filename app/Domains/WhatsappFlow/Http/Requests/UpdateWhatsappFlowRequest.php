<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsappFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'whatsapp_line_id' => ['nullable', 'integer', 'exists:whatsapp_lines,id'],
            'on_submit_action' => ['nullable', 'in:create_lead,update_contact,webhook,none'],
            'on_submit_webhook_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
