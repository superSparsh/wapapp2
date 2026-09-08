<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInboxMediaRequest extends FormRequest
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
            'file' => ['nullable', 'file', 'max:25600'],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'media_type' => ['required', 'string', 'in:image,video,audio,document'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
