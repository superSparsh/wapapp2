<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Requests;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderKeyRequest extends FormRequest
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
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'api_key' => ['required', 'string'],
            'chat_model' => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
            'embedding_dimensions' => ['nullable', 'integer', 'min:128', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
