<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Http\Requests\Concerns\ValidatesEditableTemplateName;
use Illuminate\Foundation\Http\FormRequest;

class SaveAuthRequest extends FormRequest
{
    use ValidatesEditableTemplateName;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->editableNameRules(), [
            'copy_button_text' => ['nullable', 'string', 'max:25'],
            'auto_fill' => ['nullable', 'boolean'],
            'zero_tap' => ['nullable', 'boolean'],
            'zero_tap_accepted' => ['nullable', 'boolean'],
            'fill_button_text' => ['nullable', 'string', 'max:20'],
            'message_validity' => ['nullable', 'boolean'],
            'validity_seconds' => ['nullable', 'integer', 'min:30', 'max:900'],
            'expiration_time' => ['nullable', 'boolean'],
            'expiration_minutes' => ['nullable', 'integer', 'min:1', 'max:90'],
            'add_secret_recommendation' => ['nullable', 'boolean'],
            'supported_apps' => ['nullable', 'array', 'max:5'],
            'supported_apps.*.package_name' => ['nullable', 'string', 'max:255'],
            'supported_apps.*.signature_hash' => ['nullable', 'string', 'size:11'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateEditableNameUnique($validator);

            if ($this->boolean('auto_fill') && $this->boolean('zero_tap') && ! $this->boolean('zero_tap_accepted')) {
                $validator->errors()->add('zero_tap_accepted', 'You must accept the zero-tap terms.');
            }

            if ($this->boolean('auto_fill') && ! $this->boolean('zero_tap') && blank($this->input('fill_button_text'))) {
                $validator->errors()->add('fill_button_text', 'Autofill button text is required.');
            }
        });
    }

    public function messages(): array
    {
        return $this->editableNameMessages();
    }
}
