<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Requests;

use App\Models\TeamMember;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('web')->check() || auth('team')->check();
    }

    public function rules(): array
    {
        /** @var TeamMember|null $member */
        $member = $this->route('teamMember');

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('team_members', 'phone')
                    ->whereNull('deleted_at')
                    ->ignore($member?->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'whatsapp_line_ids' => ['nullable', 'array'],
            'whatsapp_line_ids.*' => PublicId::uuidExistsRules(WhatsappLine::class, nullable: false),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNormalizer::normalize($this->input('phone')),
        ]);
    }
}
