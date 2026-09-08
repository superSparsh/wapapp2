<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordInboundMessageRequest extends FormRequest
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
            'line_id' => ['required', 'integer'],
            'line_phone' => ['nullable', 'string', 'max:30'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'integer'],
            'body' => ['required', 'string', 'max:4096'],
            'external_message_id' => ['nullable', 'string', 'max:100'],
            'message_type' => ['nullable', 'string', 'in:text,image,video,audio,document,location,contact,sticker,interactive,system'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
