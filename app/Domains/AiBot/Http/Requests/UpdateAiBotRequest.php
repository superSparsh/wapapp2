<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Requests;

use App\Domains\Integration\Services\PhoneLineService;
use App\Enums\AiProvider;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiBotRequest extends FormRequest
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
        $lineLocked = PhoneLineService::lockedLineId() !== null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string'],
            'provider' => ['sometimes', Rule::in(AiProvider::selectable())],
            'chat_model' => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'business_information' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
            'whatsapp_line_id' => $lineLocked
                ? ['sometimes', 'nullable', 'uuid', Rule::exists(WhatsappLine::class, 'uuid')]
                : ['sometimes', 'required', 'uuid', Rule::exists(WhatsappLine::class, 'uuid')],
        ];
    }
}
