<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePaymentConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        // If a config already exists, secret can be omitted (keep current encrypted value)
        $secretRules = \App\Domains\Commerce\Models\PaymentConfig::query()->exists()
            ? ['nullable', 'string', 'max:191']
            : ['required', 'string', 'max:191'];

        return [
            'client_name'              => ['required', 'string', 'max:191'],
            'razorpay_key'             => ['required', 'string', 'max:191'],
            'razorpay_secret'          => $secretRules,
            'payment_template_id'      => ['nullable', 'integer', 'exists:templates,id'],
            'confirmation_template_id' => ['nullable', 'integer', 'exists:templates,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_name.required'     => 'Client name is required.',
            'razorpay_key.required'    => 'Razorpay key is required.',
            'razorpay_secret.required' => 'Razorpay secret is required.',
        ];
    }
}
