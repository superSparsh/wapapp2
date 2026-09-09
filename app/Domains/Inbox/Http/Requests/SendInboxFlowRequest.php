<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInboxFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'flow_id' => ['required', 'integer', 'exists:whatsapp_flows,id'],
            'body' => ['required', 'string', 'max:1024'],
            'flow_cta' => ['required', 'string', 'max:30'],
        ];
    }
}
