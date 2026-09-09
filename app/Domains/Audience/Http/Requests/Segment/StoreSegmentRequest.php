<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Requests\Segment;

use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;

class StoreSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class),
            'match_type' => ['nullable', 'string', 'in:all,any'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required_with:conditions', 'string', 'max:100'],
            'conditions.*.type' => ['required_with:conditions', 'string', 'in:equals,not_equals,contains,starts_with,ends_with,greater_than,less_than,is_empty,is_not_empty'],
            'conditions.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }
}
