<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInboxInteractiveComposerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type');

        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    'button',
                    'list',
                    'product',
                    'product_list',
                    'catalog_message',
                    'cta_url',
                    'location_request_message',
                    'address_message',
                ]),
            ],
            'body' => [$type === 'product' ? 'nullable' : 'required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'header' => ['nullable', 'string', 'max:60'],
            'header_text' => ['nullable', 'string', 'max:60'],
            'button_text' => ['required_if:type,cta_url', 'nullable', 'string', 'max:20'],
            'url' => ['required_if:type,cta_url', 'nullable', 'url', 'max:2000'],
            'country' => ['nullable', 'string', 'size:2'],
            'catalog_id' => ['required_if:type,product,product_list', 'nullable', 'string', 'max:120'],
            'product_retailer_id' => ['required_if:type,product', 'nullable', 'string', 'max:120'],
            'product_retailer_ids' => ['required_if:type,product_list', 'nullable', 'array', 'min:1', 'max:10'],
            'product_retailer_ids.*' => ['required', 'string', 'max:120'],
            'section_title' => ['nullable', 'string', 'max:24'],
            'buttons' => ['required_if:type,button', 'nullable', 'array', 'min:1', 'max:3'],
            'buttons.*.id' => ['nullable', 'string', 'max:256'],
            'buttons.*.title' => ['required_with:buttons', 'string', 'max:20'],
            'list_button_text' => ['required_if:type,list', 'nullable', 'string', 'max:20'],
            'sections' => ['required_if:type,list', 'nullable', 'array', 'min:1', 'max:10'],
            'sections.*.title' => ['nullable', 'string', 'max:24'],
            'sections.*.rows' => ['required_with:sections', 'array', 'min:1', 'max:10'],
            'sections.*.rows.*.id' => ['nullable', 'string', 'max:200'],
            'sections.*.rows.*.title' => ['required', 'string', 'max:24'],
            'sections.*.rows.*.description' => ['nullable', 'string', 'max:72'],
        ];
    }
}
