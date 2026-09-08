<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInboxTemplateRequest extends FormRequest
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
            'template_code' => ['required', 'string', 'max:255'],
            'template_params' => ['nullable', 'array'],
            'language' => ['nullable', 'string', 'max:16'],
        ];
    }
}
