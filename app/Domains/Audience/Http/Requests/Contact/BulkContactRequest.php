<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class BulkContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:contacts,id'],
        ];
    }
}
