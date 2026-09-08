<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Support\TemplateNameValidator;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBodyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'body_text' => ['required', 'string', 'max:'.config('templates.body_limit', 1024)],
            'samples' => ['nullable', 'array'],
            'samples.*' => ['nullable', 'string'],
        ];

        $template = $this->route('template');

        if ($template instanceof Template && ! $template->isSetupComplete()) {
            $categories = TemplateCategoryCatalog::builderValues();

            $rules = array_merge($rules, [
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
                'category' => ['required', 'string', Rule::in($categories)],
                'language' => ['required', 'string', Rule::in(['en_GB', 'en_US'])],
                'template_type' => ['required', 'in:regular,auto_response'],
            ]);
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $template = $this->route('template');

            if (! $template instanceof Template || $template->isSetupComplete()) {
                return;
            }

            $name = (string) $this->input('name', '');

            if ($name === '') {
                return;
            }

            if (TemplateNameValidator::nameExistsForLine($name, $template->whatsapp_line_id, $template->id)) {
                $validator->errors()->add(
                    'name',
                    'A template with this name already exists. Please choose a different name.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'body_text.required' => 'Template body text is required.',
            'body_text.max' => 'Body text exceeds the maximum length allowed by WhatsApp.',
            'name.regex' => 'Template name must use lowercase letters, numbers, and underscores only.',
            'name.required' => 'Template name is required.',
            'category.required' => 'Category is required.',
            'language.required' => 'Language is required.',
            'template_type.required' => 'Template type is required.',
        ];
    }
}
