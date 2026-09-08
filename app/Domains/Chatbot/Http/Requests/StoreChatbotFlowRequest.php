<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatbotFlowRequest extends FormRequest
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
            'whatsapp_line_id' => ['nullable', 'integer', 'exists:whatsapp_lines,id'],
        ];
    }
}
