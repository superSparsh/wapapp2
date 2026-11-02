<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Requests;

use App\Enums\DataPrincipalRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDataPrincipalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $statuses = array_map(
            static fn (DataPrincipalRequestStatus $status): string => $status->value,
            DataPrincipalRequestStatus::cases(),
        );

        return [
            'status' => ['required', Rule::in($statuses)],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
