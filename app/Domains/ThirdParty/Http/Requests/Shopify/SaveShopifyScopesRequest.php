<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\Shopify;

use Illuminate\Foundation\Http\FormRequest;

class SaveShopifyScopesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'access_scope_check'        => ['nullable', 'boolean'],
            'product_listings_add'      => ['nullable', 'boolean'],
            'product_listings_remove'   => ['nullable', 'boolean'],
            'product_listings_update'   => ['nullable', 'boolean'],
            'products_create'           => ['nullable', 'boolean'],
            'products_delete'           => ['nullable', 'boolean'],
            'products_update'           => ['nullable', 'boolean'],
            'template_selected'         => ['nullable', 'string', 'max:255'],
            'mail_list_id'              => ['nullable', 'integer'],
        ];
    }
}
