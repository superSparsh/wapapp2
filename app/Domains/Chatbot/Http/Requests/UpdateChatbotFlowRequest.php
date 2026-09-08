<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Requests;

use App\Enums\ChatbotFlowStatus;
use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatbotFlowRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::enum(ChatbotFlowStatus::class)],
            'whatsapp_line_id' => PublicId::uuidExistsRules(WhatsappLine::class),
        ];
    }
}
