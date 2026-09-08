<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleInboxResponseTypeRequest extends FormRequest
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
            'ai_enabled' => ['required', 'boolean'],
            'line_id' => ['nullable', 'integer'],
            'lookback_days' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string', 'in:all,unread,mine'],
            'assignee_filter' => ['nullable', 'array'],
        ];
    }
}
