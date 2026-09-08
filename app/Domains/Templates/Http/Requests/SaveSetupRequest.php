<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Support\TemplateLanguageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categories = config('templates.categories', ['MARKETING', 'UTILITY', 'AUTHENTICATION']);
        $languages = array_keys(TemplateLanguageCatalog::options());

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', 'string', Rule::in($categories)],
            'language' => ['required', 'string', Rule::in($languages)],
            'template_type' => ['required', 'in:regular,auto_response'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Template name must use lowercase letters, numbers, and underscores only.',
        ];
    }
}
