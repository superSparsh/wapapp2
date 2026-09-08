<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Requests;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiBotRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string'],
            'provider' => ['sometimes', Rule::enum(AiProvider::class)],
            'chat_model' => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'business_information' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
            'whatsapp_line_id' => ['nullable', 'integer', 'exists:whatsapp_lines,id'],
        ];
    }
}
