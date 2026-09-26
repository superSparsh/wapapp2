<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Requests;

use App\Models\MailList;
use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookSubscriptionRequest extends FormRequest
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
            'whatsapp_line_id' => ['nullable', 'integer', Rule::exists(WhatsappLine::class, 'id')],
            'audience_list_id' => PublicId::uuidExistsRules(MailList::class),
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:new_lead'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
