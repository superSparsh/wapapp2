<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:500'],
            'description' => ['required', 'string', 'max:255'],
            'audience_list_id' => ['nullable', 'integer', 'exists:mail_lists,id'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:new_lead'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
