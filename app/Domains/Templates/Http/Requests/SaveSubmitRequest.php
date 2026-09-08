<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.accepted' => 'You must confirm the submission before proceeding.',
        ];
    }
}
