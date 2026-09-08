<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportTeamMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('team')->check();
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:'.(int) config('team.import.max_size_kb', 5120),
            ],
        ];
    }
}
