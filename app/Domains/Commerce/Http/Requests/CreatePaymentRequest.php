<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'customer_name'  => ['required', 'string', 'max:191'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'amount'         => ['required', 'numeric', 'min:1', 'max:5000000'],
            'currency'       => ['nullable', 'string', 'size:3'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_name.required'  => 'Customer name is required.',
            'customer_phone.required' => 'Customer phone number is required.',
            'customer_phone.regex'    => 'Phone number must contain only digits, +, -, spaces, or parentheses.',
            'amount.required'         => 'Amount is required.',
            'amount.numeric'          => 'Amount must be a number.',
            'amount.min'              => 'Amount must be at least ₹1.',
        ];
    }

    public function validatedAmount(): float
    {
        return (float) preg_replace('/[^0-9.]/', '', (string) $this->input('amount'));
    }
}
