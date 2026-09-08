<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInboxMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4096'],
        ];
    }
}
