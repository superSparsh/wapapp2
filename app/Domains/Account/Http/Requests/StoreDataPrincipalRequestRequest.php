<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Requests;

use App\Enums\DataPrincipalRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataPrincipalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $types = array_map(
            static fn (DataPrincipalRequestType $type): string => $type->value,
            DataPrincipalRequestType::selectable(),
        );

        return [
            'request_type' => ['required', Rule::in($types)],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'requester_phone' => ['nullable', 'string', 'max:32'],
            'nominee_name' => ['nullable', 'string', 'max:191'],
            'nominee_email' => ['nullable', 'email', 'max:191'],
            'nominee_phone' => ['nullable', 'string', 'max:32'],
            'nominee_relation' => ['nullable', 'string', 'max:128'],
        ];
    }
}
