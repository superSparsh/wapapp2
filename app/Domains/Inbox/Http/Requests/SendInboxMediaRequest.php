<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInboxMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('whatsapp.media.max_size_kb', 16384);

        return [
            'media_type' => ['required', 'string', Rule::in(['image', 'video', 'audio', 'document'])],
            'file' => ['required', 'file', 'max:'.$maxKb],
            'caption' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
