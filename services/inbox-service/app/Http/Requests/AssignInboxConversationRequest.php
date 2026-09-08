<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignInboxConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'assignee' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'team_member_id' => ['nullable', 'integer'],
        ];
    }
}
