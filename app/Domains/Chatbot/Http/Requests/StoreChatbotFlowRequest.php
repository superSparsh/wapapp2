<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Requests;

use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChatbotFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_line_id' => PublicId::uuidExistsRules(WhatsappLine::class),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (WhatsappLine::query()->count() <= 1) {
                return;
            }

            if ($this->filled('whatsapp_line_id')) {
                return;
            }

            $validator->errors()->add(
                'whatsapp_line_id',
                'Please choose which WhatsApp number this chatbot should use.',
            );
        });
    }
}
