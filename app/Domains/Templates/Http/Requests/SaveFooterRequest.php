<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveFooterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'footer_text' => ['nullable', 'string', 'max:'.config('templates.footer_limit', 60)],
        ];
    }

    public function messages(): array
    {
        return [
            'footer_text.max' => 'Footer text exceeds the maximum length allowed by WhatsApp.',
        ];
    }
}
