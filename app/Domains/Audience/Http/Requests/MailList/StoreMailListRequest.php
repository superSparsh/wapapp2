<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\MailList;

use Illuminate\Foundation\Http\FormRequest;

class StoreMailListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:mail_lists,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
