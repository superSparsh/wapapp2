<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Http\Requests\Concerns\ValidatesEditableTemplateName;
use Illuminate\Foundation\Http\FormRequest;

class SaveSubmitRequest extends FormRequest
{
    use ValidatesEditableTemplateName;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->editableNameRules(), [
            'confirm' => ['accepted'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateEditableNameUnique($validator));
    }

    public function messages(): array
    {
        return array_merge($this->editableNameMessages(), [
            'confirm.accepted' => 'You must confirm the submission before proceeding.',
        ]);
    }
}
