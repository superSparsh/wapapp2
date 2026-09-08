<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\VariableDataType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'data_type' => ['required', 'string', Rule::in(array_column(VariableDataType::cases(), 'value'))],
            'value' => ['nullable', 'string'],
        ];
    }
}
