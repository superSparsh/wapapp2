<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\Contact;

use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mailListId = null;
        if ($this->filled('mail_list_id')) {
            $mailListId = PublicId::find(MailList::class, (string) $this->input('mail_list_id'))?->id;
        }

        return [
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{7,20}$/',
                Rule::unique('contacts', 'phone')
                    ->where(
                        static fn ($query) => $query->where('mail_list_id', $mailListId),
                    )
                    ->withoutTrashed(),
            ],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:191'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class),
            'source' => ['nullable', 'string', 'max:64'],
            'send_opt_in_message' => ['nullable', 'string', 'in:yes,no'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already on the selected list. The same number can still be added to a different list.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '';
        $countryCode = preg_replace('/\D+/', '', (string) $this->input('country_code', '')) ?? '';

        if ($phone !== '' && $countryCode !== '' && ! str_starts_with($phone, $countryCode)) {
            $phone = $countryCode.$phone;
        }

        if ($phone !== '') {
            // Match import / inbox normalization (10-digit → 91…).
            if (strlen($phone) === 10) {
                $phone = '91'.$phone;
            }
            $this->merge(['phone' => $phone]);
        }
    }
}
