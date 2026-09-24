<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Http\Requests\Concerns\ValidatesEditableTemplateName;
use Illuminate\Foundation\Http\FormRequest;

class SaveLtoRequest extends FormRequest
{
    use ValidatesEditableTemplateName;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->editableNameRules(), [
            'discount_introduction' => ['required', 'string', 'max:60'],
            'expiration_time' => ['nullable', 'boolean'],
            'time_variable' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'coupon_code' => ['nullable', 'string', 'max:15'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateEditableNameUnique($validator));
    }

    public function messages(): array
    {
        return $this->editableNameMessages();
    }
}
