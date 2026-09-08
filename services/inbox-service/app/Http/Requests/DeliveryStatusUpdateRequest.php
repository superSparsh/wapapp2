<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'external_message_id' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:sent,delivered,read,failed'],
            'failed_reason' => ['nullable', 'string', 'max:500'],
            'timestamp' => ['nullable', 'date'],
        ];
    }
}
