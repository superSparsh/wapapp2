<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\Contact;

use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,20}$/'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:191'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class),
            'source' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
