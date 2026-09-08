<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveFlowJsonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'flow_json' => ['required', 'array'],
            'flow_json.screens' => ['required', 'array'],
            'flow_json.first_screen' => ['nullable', 'string'],
        ];
    }
}
