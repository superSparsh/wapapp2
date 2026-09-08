<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\Shopify;

use Illuminate\Foundation\Http\FormRequest;

class SaveShopifyDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'domainurl' => ['required', 'url', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'domainurl.required' => 'Shopify store domain URL is required.',
            'domainurl.url'      => 'Please enter a valid URL (e.g. https://yourstore.myshopify.com).',
        ];
    }
}
