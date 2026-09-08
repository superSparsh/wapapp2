<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Requests;

use App\Enums\BusinessInfoContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessInfoRequest extends FormRequest
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
            'ai_bot_id' => ['sometimes', 'integer', 'exists:ai_bots,id'],
            'title' => ['required', 'string', 'max:255'],
            'content_type' => ['sometimes', Rule::enum(BusinessInfoContentType::class)],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'], // 10MB max
        ];
    }
}
