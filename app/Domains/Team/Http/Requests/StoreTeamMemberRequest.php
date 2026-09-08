<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Requests;

use App\Enums\TeamMemberRole;
use App\Models\TeamMember;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('web')->check() || auth('team')->check();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('team_members', 'email'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(TeamMemberRole::class)],
            'whatsapp_line_ids' => ['nullable', 'array'],
            'whatsapp_line_ids.*' => PublicId::uuidExistsRules(WhatsappLine::class, nullable: false),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower((string) $this->input('email')),
            'phone' => PhoneNormalizer::normalize((string) $this->input('phone')),
        ]);
    }
}
