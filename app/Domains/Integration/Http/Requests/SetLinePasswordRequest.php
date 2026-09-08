<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Requests;

use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;

class SetLinePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'line' => PublicId::uuidExistsRules(WhatsappLine::class, nullable: false),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
