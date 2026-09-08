<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use App\Enums\ConversationResponseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInboxContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'max:6'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\d{6,15}$/'],
            'response_type' => ['required', 'string', Rule::in([
                ConversationResponseType::Human->value,
                ConversationResponseType::Ai->value,
            ])],
        ];
    }

    public function fullPhone(): string
    {
        $countryCode = preg_replace('/\D+/', '', (string) $this->validated('country_code')) ?? '';
        $phone = preg_replace('/\D+/', '', (string) $this->validated('phone')) ?? '';

        return $countryCode.$phone;
    }
}
