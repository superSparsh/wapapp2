<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\Shopify;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveShopifyScopesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $scopeKeys = array_keys(config('shopify.scopes', []));

        return [
            // Single-scope upsert (UI)
            'scope_key' => ['nullable', 'string', Rule::in($scopeKeys)],
            'enabled' => ['nullable', 'boolean'],
            'template_id' => ['nullable'],
            'mail_list_id' => ['nullable', 'integer'],

            // Bulk legacy-shaped payload
            'webhookdatascopes' => ['nullable', 'array'],
            'webhookdatascopes.*.key' => ['required_with:webhookdatascopes', 'string'],
            'webhookdatascopes.*.value' => ['required_with:webhookdatascopes', 'string', 'in:yes,no'],
            'webhookdatatemplate' => ['nullable', 'array'],
            'webhookdatatemplate.*.key' => ['required_with:webhookdatatemplate', 'string'],
            'webhookdatatemplate.*.value' => ['nullable'],
            'selectedScope' => ['nullable', 'string'],
            'maillistid' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enabled')) {
            $this->merge([
                'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }
}
