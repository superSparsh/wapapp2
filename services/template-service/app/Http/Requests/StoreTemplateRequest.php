<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\TemplateCategoryCatalog;
use App\Support\TemplateLanguageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(TemplateCategoryCatalog::builderValues())],
            'language' => ['required', 'string', Rule::in(array_keys(TemplateLanguageCatalog::options()))],
            'template_type' => ['nullable', 'string'],
        ];
    }
}
