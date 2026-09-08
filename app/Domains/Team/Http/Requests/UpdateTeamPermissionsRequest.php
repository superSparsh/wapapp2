<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('web')->check() || auth('team')->check();
    }

    public function rules(): array
    {
        $rules = [];

        foreach (array_keys(config('team.permissions', [])) as $permission) {
            $rules['permissions.'.$permission] = ['nullable', 'boolean'];
        }

        $rules['member_uuids'] = ['nullable', 'array'];
        $rules['member_uuids.*'] = ['uuid'];

        return $rules;
    }

    /** @return array<string, bool> */
    public function permissionsInput(): array
    {
        return (array) $this->input('permissions', []);
    }

    /** @return array<int, string> */
    public function memberUuids(): array
    {
        return array_values(array_filter((array) $this->input('member_uuids', [])));
    }
}
