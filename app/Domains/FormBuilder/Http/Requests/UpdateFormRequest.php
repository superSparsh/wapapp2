<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Http\Requests;

use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = $this->input('fields');
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            if (is_array($decoded)) {
                $fields = $decoded;
            }
        }

        if (is_array($fields)) {
            $this->merge(['fields' => FormFieldNormalizer::normalizeList($fields)]);
        }
    }

    public function rules(): array
    {
        $maxFields = (int) config('form-builder.max_fields', 20);
        $maxOptions = (int) config('form-builder.max_dropdown_options', 10);
        $labelLimit = (int) config('form-builder.field_label_limit', 255);
        $textLimit = (int) config('form-builder.field_text_limit', 1024);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'list_id' => ['sometimes', 'required', 'integer'],
            'template_id' => ['sometimes', 'required', 'integer'],
            'activate' => ['boolean'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'redirect_url' => ['nullable', 'string', 'max:500'],
            'custom_css' => ['nullable', 'string'],
            'embed_settings' => ['nullable', 'array'],
            'embed_settings.show_required_only' => ['boolean'],
            'embed_settings.include_js' => ['boolean'],
            'embed_settings.include_css' => ['boolean'],
            'embed_settings.show_invisible_fields' => ['boolean'],

            'fields' => ['nullable', 'array', "max:{$maxFields}"],
            'fields.*.type' => ['required', 'string', 'in:logo,header,paragraph,input,dropdown,checkbox,phone,first_name,last_name'],
            'fields.*.label' => ['required', 'string', "max:{$labelLimit}"],
            'fields.*.required' => ['boolean'],
            'fields.*.placeholder' => ['nullable', 'string', "max:{$labelLimit}"],
            'fields.*.text' => ['nullable', 'string', "max:{$textLimit}"],
            'fields.*.required_message' => ['nullable', 'string', "max:{$textLimit}"],
            'fields.*.image_path' => ['nullable', 'string', 'max:500'],
            'fields.*.options' => ['nullable', 'array', "max:{$maxOptions}"],
            'fields.*.options.*' => ['string', "max:{$labelLimit}"],
        ];
    }

    public function messages(): array
    {
        $maxFields = (int) config('form-builder.max_fields', 20);

        return [
            'name.required' => 'Form name is required.',
            'list_id.required' => 'Please select a mail list.',
            'template_id.required' => 'Please select a template.',
            'fields.max' => "Maximum {$maxFields} fields allowed per form.",
            'fields.*.type.required' => 'Each field must have a type.',
            'fields.*.label.required' => 'Each field must have a label.',
        ];
    }
}
