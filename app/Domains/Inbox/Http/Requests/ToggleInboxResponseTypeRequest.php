<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleInboxResponseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ai_enabled' => ['required', 'boolean'],
        ];
    }

    public function responseType(): string
    {
        return $this->boolean('ai_enabled') ? 'ai_response' : 'human_response';
    }
}
