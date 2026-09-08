<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\MailList;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive,suspended'],
        ];
    }
}
