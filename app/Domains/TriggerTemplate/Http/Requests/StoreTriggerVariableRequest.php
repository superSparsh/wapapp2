<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTriggerVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'variable_name' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('trigger_variables', 'variable_name'),
            ],
            'template_code' => ['required', 'string', 'max:255'],
            'template_name' => ['required', 'string', 'max:255'],
            'list_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variable_name.regex' => 'Trigger name may only contain letters, numbers, and underscores.',
            'variable_name.unique' => 'Trigger name already exists.',
        ];
    }
}
