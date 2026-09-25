<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Http\Requests;

use App\Domains\FormBuilder\Enums\FieldType;
use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use App\Models\SignupForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormSubmissionRequest extends FormRequest
{
    private ?SignupForm $resolvedForm = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $form = $this->resolveForm();
        if (! $form instanceof SignupForm) {
            return [
                'phone' => ['required', 'string', 'max:20'],
            ];
        }

        $fields = FormFieldNormalizer::normalizeList(
            is_array($form->fields) ? $form->fields : []
        );

        $rules = [];

        foreach ($fields as $index => $field) {
            $type = (string) ($field['type'] ?? '');
            if (! FormFieldNormalizer::isSubmittable($type)) {
                continue;
            }

            $inputName = FormFieldNormalizer::inputName($type, $index);
            $required = (bool) ($field['required'] ?? false) || $type === FieldType::Phone->value;

            $rules[$inputName] = match ($type) {
                FieldType::Checkbox->value => $required
                    ? ['accepted']
                    : ['nullable', 'in:1,0,true,false,on,yes'],
                FieldType::Phone->value => array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    'string',
                    'max:20',
                ])),
                FieldType::FirstName->value, FieldType::LastName->value => array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    'string',
                    'max:150',
                ])),
                FieldType::Dropdown->value => array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    'string',
                    'max:255',
                    ! empty($field['options']) ? Rule::in($field['options']) : null,
                ])),
                default => array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    'string',
                    'max:255',
                ])),
            };
        }

        if (! isset($rules['phone'])) {
            $rules['phone'] = ['required', 'string', 'max:20'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'phone.required' => 'WhatsApp number is required.',
        ];

        $form = $this->resolveForm();
        if (! $form instanceof SignupForm) {
            return $messages;
        }

        $fields = FormFieldNormalizer::normalizeList(
            is_array($form->fields) ? $form->fields : []
        );

        foreach ($fields as $index => $field) {
            $type = (string) ($field['type'] ?? '');
            if (! FormFieldNormalizer::isSubmittable($type)) {
                continue;
            }

            $inputName = FormFieldNormalizer::inputName($type, $index);
            $label = (string) ($field['label'] ?? 'This field');
            $custom = trim((string) ($field['required_message'] ?? ''));

            if ($custom !== '') {
                $messages[$inputName.'.required'] = $custom;
                $messages[$inputName.'.accepted'] = $custom;
            } else {
                $messages[$inputName.'.required'] = "{$label} is required.";
                $messages[$inputName.'.accepted'] = "{$label} is required.";
            }
        }

        return $messages;
    }

    public function attributes(): array
    {
        $attributes = [];

        $form = $this->resolveForm();
        if (! $form instanceof SignupForm) {
            return $attributes;
        }

        $fields = FormFieldNormalizer::normalizeList(
            is_array($form->fields) ? $form->fields : []
        );

        foreach ($fields as $index => $field) {
            $type = (string) ($field['type'] ?? '');
            if (! FormFieldNormalizer::isSubmittable($type)) {
                continue;
            }

            $inputName = FormFieldNormalizer::inputName($type, $index);
            $label = trim((string) ($field['label'] ?? ''));
            if ($label !== '') {
                $attributes[$inputName] = $label;
            }
        }

        return $attributes;
    }

    private function resolveForm(): ?SignupForm
    {
        if ($this->resolvedForm instanceof SignupForm) {
            return $this->resolvedForm;
        }

        $slug = $this->route('slug');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $this->resolvedForm = SignupForm::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();

        return $this->resolvedForm;
    }
}
